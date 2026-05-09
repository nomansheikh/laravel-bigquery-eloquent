<?php

use Google\Cloud\BigQuery\Timestamp;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryGrammar;

it('registers the bigquery connection', function () {
    $connection = DB::connection('bigquery');

    expect($connection)->toBeInstanceOf(BigQueryConnection::class)
        ->and($connection->getProjectId())->toBe('test-project')
        ->and($connection->getDefaultDataset())->toBe('default_dataset');
});

it('grammar wraps fully qualified table', function () {
    $connection = DB::connection('bigquery');
    $grammar = $connection->getQueryGrammar();

    $ref = new ReflectionClass($grammar);
    $method = $ref->getMethod('wrapTable');

    $wrapped = $method->invoke($grammar, 'my-project.my_dataset.my_table');

    expect($wrapped)->toBe('`my-project.my_dataset.my_table`');
});

it('uses the BigQueryGrammar on the connection', function () {
    $connection = DB::connection('bigquery');

    expect($connection->getQueryGrammar())->toBeInstanceOf(BigQueryGrammar::class);
});

it('uses microsecond precision for the grammar date format', function () {
    $grammar = DB::connection('bigquery')->getQueryGrammar();

    expect($grammar->getDateFormat())->toBe('Y-m-d H:i:s.u');
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
    $datetime = new DateTime('2025-01-15 10:00:00');
    $immutable = new DateTimeImmutable('2025-06-20 12:30:45');

    $normalized = $connection->normalizeBindings([$datetime, 'a string', 42, null, true, $immutable]);

    expect($normalized[0])->toBeInstanceOf(Timestamp::class)
        ->and($normalized[1])->toBe('a string')
        ->and($normalized[2])->toBe(42)
        ->and($normalized[3])->toBeNull()
        ->and($normalized[4])->toBeTrue()
        ->and($normalized[5])->toBeInstanceOf(Timestamp::class);
});

it('returns an empty array when normalizing empty bindings', function () {
    $connection = DB::connection('bigquery');

    expect($connection->normalizeBindings([]))->toBe([]);
});
