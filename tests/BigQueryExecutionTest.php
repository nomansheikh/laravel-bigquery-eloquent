<?php

use Google\Cloud\BigQuery\Numeric;
use Google\Cloud\BigQuery\Timestamp;
use Google\Cloud\Core\Exception\BadRequestException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryQueryBuilder;
use NomanSheikh\LaravelBigqueryEloquent\Tests\Support\BigQuerySpy;

function bigQuery(): BigQueryConnection
{
    return DB::connection('bigquery');
}

it('returns objects from select', function () {
    BigQuerySpy::attach(bigQuery(), [['id' => 1, 'name' => 'foo']]);

    $rows = bigQuery()->select('select * from `p`.`d`.`t`');

    expect($rows)->toHaveCount(1)
        ->and($rows[0])->toBeInstanceOf(stdClass::class)
        ->and($rows[0]->name)->toBe('foo');
});

it('unwraps BigQuery value objects into plain PHP values', function () {
    BigQuerySpy::attach(bigQuery(), [[
        'ts' => new Timestamp(new DateTimeImmutable('2026-01-02 03:04:05.000000', new DateTimeZone('UTC'))),
        'amount' => new Numeric('1.50'),
        'tags' => ['a', 'b'],
    ]]);

    $row = bigQuery()->select('select * from `p`.`d`.`t`')[0];

    expect($row->ts)->toBe('2026-01-02 03:04:05.000000+00:00')
        ->and($row->amount)->toBe('1.50')
        ->and($row->tags)->toBe(['a', 'b']);
});

it('honors the columns passed to get', function () {
    $spy = BigQuerySpy::attach(bigQuery());

    bigQuery()->table('users')->get(['id', 'name']);

    expect($spy->sql)->toBe('select `id`, `name` from `test-project`.`default_dataset`.`users`');
});

it('returns objects from the query builder', function () {
    BigQuerySpy::attach(bigQuery(), [['id' => 1]]);

    expect(bigQuery()->table('users')->get()->first())->toBeInstanceOf(stdClass::class);
});

it('supports pluck without a preceding get', function () {
    BigQuerySpy::attach(bigQuery(), [['user_id' => 7], ['user_id' => 9]]);

    expect(bigQuery()->table('users')->pluck('user_id')->all())->toBe([7, 9]);
});

it('does not touch BigQuery while pretending', function () {
    $spy = BigQuerySpy::attach(bigQuery());

    $queries = bigQuery()->pretend(function ($connection) {
        $connection->select('select 1');
        $connection->table('users')->where('id', 1)->delete();
    });

    expect($spy->ranQuery)->toBeFalse()
        ->and($queries)->toHaveCount(2);
});

it('wraps BigQuery failures in a QueryException', function () {
    BigQuerySpy::attach(bigQuery(), failWith: new BadRequestException('bad query'));

    bigQuery()->select('select 1');
})->throws(QueryException::class, 'bad query');

it('runs a raw statement', function () {
    $spy = BigQuerySpy::attach(bigQuery());

    expect(bigQuery()->statement('create table `p`.`d`.`t` (id INT64)'))->toBeTrue()
        ->and($spy->sql)->toBe('create table `p`.`d`.`t` (id INT64)');
});

it('runs unprepared statements', function () {
    $spy = BigQuerySpy::attach(bigQuery());

    expect(bigQuery()->unprepared('drop table `p`.`d`.`t`'))->toBeTrue()
        ->and($spy->sql)->toBe('drop table `p`.`d`.`t`');
});

it('returns affected rows from a raw affecting statement', function () {
    BigQuerySpy::attach(bigQuery(), info: ['numDmlAffectedRows' => '4']);

    expect(bigQuery()->update('update `p`.`d`.`t` set a = ? where id = ?', [1, 2]))->toBe(4);
});

it('streams rows through cursor', function () {
    BigQuerySpy::attach(bigQuery(), [['id' => 1], ['id' => 2]]);

    $ids = [];

    foreach (bigQuery()->cursor('select * from `p`.`d`.`t`') as $row) {
        $ids[] = $row->id;
    }

    expect($ids)->toBe([1, 2]);
});

it('inlines null bindings because BigQuery rejects untyped null parameters', function () {
    $spy = BigQuerySpy::attach(bigQuery(), info: ['numDmlAffectedRows' => '1']);

    bigQuery()->table('users')->where('id', 5)->update(['name' => null, 'age' => 30]);

    expect($spy->sql)->toBe('update `test-project`.`default_dataset`.`users` set `name` = null, `age` = ? where `id` = ?')
        ->and($spy->parameters)->toBe([30, 5]);
});

it('does not mistake a question mark inside a string literal for a placeholder', function () {
    $spy = BigQuerySpy::attach(bigQuery(), info: ['numDmlAffectedRows' => '1']);

    bigQuery()->update("update `p`.`d`.`t` set label = 'what? really', note = ?, extra = ? where id = ?", [null, 'x', 1]);

    expect($spy->sql)->toBe("update `p`.`d`.`t` set label = 'what? really', note = null, extra = ? where id = ?")
        ->and($spy->parameters)->toBe(['x', 1]);
});

it('converts datetime bindings to BigQuery timestamps', function () {
    $spy = BigQuerySpy::attach(bigQuery());

    bigQuery()->table('users')->where('created_at', '>', new DateTimeImmutable('2026-01-01'))->get();

    expect($spy->parameters[0])->toBeInstanceOf(Timestamp::class);
});

it('applies the configured cost guards to every job', function () {
    config()->set('database.connections.bigquery_guarded', [
        'driver' => 'bigquery',
        'project_id' => 'test-project',
        'dataset' => 'default_dataset',
        'maximum_bytes_billed' => 1000000,
        'job_timeout_ms' => 30000,
        'labels' => ['team' => 'analytics'],
    ]);

    $connection = DB::connection('bigquery_guarded');
    $spy = BigQuerySpy::attach($connection);

    $connection->select('select 1');

    expect($spy->jobOptions)->toBe([
        'maximumBytesBilled' => 1000000,
        'jobTimeoutMs' => 30000,
        'labels' => ['team' => 'analytics'],
    ]);
});

it('passes the configured location to the BigQuery client', function () {
    config()->set('database.connections.bigquery_eu', [
        'driver' => 'bigquery',
        'project_id' => 'test-project',
        'dataset' => 'default_dataset',
        'location' => 'EU',
    ]);

    $location = new ReflectionProperty(DB::connection('bigquery_eu')->getClient(), 'location');

    expect($location->getValue(DB::connection('bigquery_eu')->getClient()))->toBe('EU');
});

it('builds a BigQuery query builder from query() and table()', function () {
    expect(bigQuery()->query())->toBeInstanceOf(BigQueryQueryBuilder::class)
        ->and(bigQuery()->table('users', 'u')->toSql())
        ->toBe('select * from `test-project`.`default_dataset`.`users` as `u`');
});

it('scopes delete($id) to the table reference BigQuery understands', function () {
    $spy = BigQuerySpy::attach(bigQuery(), info: ['numDmlAffectedRows' => '1']);

    expect(bigQuery()->table('users')->delete(42))->toBe(1)
        ->and($spy->sql)->toBe('delete from `test-project`.`default_dataset`.`users` where `users`.`id` = ?')
        ->and($spy->parameters)->toBe([42]);
});

it('keeps raw expressions out of update bindings', function () {
    $spy = BigQuerySpy::attach(bigQuery(), info: ['numDmlAffectedRows' => '1']);

    bigQuery()->table('users')->where('id', 5)->update(['visits' => DB::raw('visits + 1')]);

    expect($spy->sql)->toBe('update `test-project`.`default_dataset`.`users` set `visits` = visits + 1 where `id` = ?')
        ->and($spy->parameters)->toBe([5]);
});
