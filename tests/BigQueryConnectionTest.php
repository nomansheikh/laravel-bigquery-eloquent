<?php

use Google\Cloud\BigQuery\Timestamp;
use Illuminate\Support\Facades\DB;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryGrammar;
use NomanSheikh\LaravelBigqueryEloquent\Tests\Support\BigQuerySpy;

it('registers the bigquery connection', function () {
    $connection = DB::connection('bigquery');

    expect($connection)->toBeInstanceOf(BigQueryConnection::class)
        ->and($connection->getProjectId())->toBe('test-project')
        ->and($connection->getDefaultDataset())->toBe('default_dataset')
        ->and($connection->getDriverName())->toBe('bigquery')
        ->and($connection->getQueryGrammar())->toBeInstanceOf(BigQueryGrammar::class);
});

it('uses microsecond precision for the grammar date format', function () {
    expect(DB::connection('bigquery')->getQueryGrammar()->getDateFormat())->toBe('Y-m-d H:i:s.u');
});

it('throws when getPdo is called', function () {
    DB::connection('bigquery')->getPdo();
})->throws(LogicException::class, 'BigQuery does not use PDO');

it('throws when getReadPdo is called', function () {
    DB::connection('bigquery')->getReadPdo();
})->throws(LogicException::class, 'BigQuery does not use PDO');

it('refuses transaction()', function () {
    DB::connection('bigquery')->transaction(fn () => null);
})->throws(LogicException::class, 'BigQuery does not support transactions');

it('refuses beginTransaction()', function () {
    DB::connection('bigquery')->beginTransaction();
})->throws(LogicException::class, 'BigQuery does not support transactions');

it('refuses commit()', function () {
    DB::connection('bigquery')->commit();
})->throws(LogicException::class, 'BigQuery does not support transactions');

it('refuses rollBack()', function () {
    DB::connection('bigquery')->rollBack();
})->throws(LogicException::class, 'BigQuery does not support transactions');

it('falls back to package config when connection config omits keys', function () {
    config()->set('bigquery-eloquent.project_id', 'fallback-project');
    config()->set('bigquery-eloquent.dataset', 'fallback_dataset');
    config()->set('database.connections.bigquery_fallback', [
        'driver' => 'bigquery',
    ]);

    $connection = DB::connection('bigquery_fallback');

    expect($connection->getProjectId())->toBe('fallback-project')
        ->and($connection->getDefaultDataset())->toBe('fallback_dataset');
});

it('prefers connection config over package config', function () {
    config()->set('bigquery-eloquent.project_id', 'fallback-project');
    config()->set('database.connections.bigquery_explicit', [
        'driver' => 'bigquery',
        'project_id' => 'explicit-project',
        'dataset' => 'explicit_dataset',
    ]);

    expect(DB::connection('bigquery_explicit')->getProjectId())->toBe('explicit-project');
});

it('accepts an array key_file without throwing', function () {
    config()->set('database.connections.bigquery_array_key', [
        'driver' => 'bigquery',
        'project_id' => 'test-project',
        'dataset' => 'default_dataset',
        'key_file' => [
            'type' => 'service_account',
            'project_id' => 'test-project',
            'private_key_id' => 'fake',
            'private_key' => 'fake',
            'client_email' => 'fake@example.com',
            'client_id' => 'fake',
        ],
    ]);

    expect(fn () => DB::connection('bigquery_array_key'))->not->toThrow(Throwable::class);
});

it('accepts a string key_file path without throwing', function () {
    config()->set('database.connections.bigquery_string_key', [
        'driver' => 'bigquery',
        'project_id' => 'test-project',
        'dataset' => 'default_dataset',
        'key_file' => '/tmp/nonexistent-but-not-touched-on-construct.json',
    ]);

    expect(fn () => DB::connection('bigquery_string_key'))->not->toThrow(Throwable::class);
});

it('normalizes DateTimeInterface bindings to BigQuery Timestamp', function () {
    $connection = DB::connection('bigquery');

    $normalized = $connection->normalizeBindings([
        new DateTime('2025-01-15 10:00:00'),
        'a string',
        42,
        null,
        true,
        new DateTimeImmutable('2025-06-20 12:30:45'),
    ]);

    expect($normalized[0])->toBeInstanceOf(Timestamp::class)
        ->and($normalized[1])->toBe('a string')
        ->and($normalized[2])->toBe(42)
        ->and($normalized[3])->toBeNull()
        ->and($normalized[4])->toBeTrue()
        ->and($normalized[5])->toBeInstanceOf(Timestamp::class);
});

it('returns an empty array when normalizing empty bindings', function () {
    expect(DB::connection('bigquery')->normalizeBindings([]))->toBe([]);
});

it('update returns numDmlAffectedRows as int', function () {
    $connection = DB::connection('bigquery');
    BigQuerySpy::attach($connection, info: ['numDmlAffectedRows' => '7']);

    expect($connection->table('users')->where('id', 1)->update(['name' => 'foo']))->toBe(7);
});

it('delete returns numDmlAffectedRows as int', function () {
    $connection = DB::connection('bigquery');
    BigQuerySpy::attach($connection, info: ['numDmlAffectedRows' => '3']);

    expect($connection->table('users')->where('id', 1)->delete())->toBe(3);
});

it('update returns 0 when DML stats are not in result info', function () {
    $connection = DB::connection('bigquery');
    BigQuerySpy::attach($connection);

    expect($connection->table('users')->where('id', 1)->update(['name' => 'foo']))->toBe(0);
});

it('insert returns true on success', function () {
    $connection = DB::connection('bigquery');
    $spy = BigQuerySpy::attach($connection);

    expect($connection->table('users')->insert(['name' => 'foo']))->toBeTrue()
        ->and($spy->sql)->toBe('insert into `test-project`.`default_dataset`.`users` (`name`) values (?)');
});

it('insert handles batch rows', function () {
    $connection = DB::connection('bigquery');
    $spy = BigQuerySpy::attach($connection);

    $inserted = $connection->table('users')->insert([
        ['name' => 'a'],
        ['name' => 'b'],
    ]);

    expect($inserted)->toBeTrue()
        ->and($spy->sql)->toBe('insert into `test-project`.`default_dataset`.`users` (`name`) values (?), (?)')
        ->and($spy->parameters)->toBe(['a', 'b']);
});

it('insert writes null columns as literals', function () {
    $connection = DB::connection('bigquery');
    $spy = BigQuerySpy::attach($connection);

    $connection->table('users')->insert(['name' => null, 'age' => 3]);

    expect($spy->sql)->toBe('insert into `test-project`.`default_dataset`.`users` (`name`, `age`) values (null, ?)')
        ->and($spy->parameters)->toBe([3]);
});

it('insert returns true on empty values without hitting the client', function () {
    $connection = DB::connection('bigquery');
    $spy = BigQuerySpy::attach($connection);

    expect($connection->table('users')->insert([]))->toBeTrue()
        ->and($spy->ranQuery)->toBeFalse();
});

it('insertGetId throws because BigQuery has no auto-increment', function () {
    DB::connection('bigquery')->table('users')->insertGetId(['name' => 'foo']);
})->throws(LogicException::class, 'BigQuery does not support auto-incrementing keys');
