<?php

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

function bigQueryTable(): Builder
{
    return DB::connection('bigquery')->table('users');
}

it('qualifies a bare table with the connection project and dataset', function () {
    expect(bigQueryTable()->toSql())
        ->toBe('select * from `test-project.default_dataset.users`');
});

it('leaves an already qualified table alone', function () {
    expect(DB::connection('bigquery')->table('other-project.other_set.events')->toSql())
        ->toBe('select * from `other-project.other_set.events`');
});

it('wraps a table alias', function () {
    expect(DB::connection('bigquery')->table('users', 'u')->select('u.id')->toSql())
        ->toBe('select `u`.`id` from `test-project.default_dataset.users` as `u`');
});

it('wraps each column segment separately', function () {
    expect(bigQueryTable()->select('u.id')->toSql())
        ->toBe('select `u`.`id` from `test-project.default_dataset.users`');
});

it('strips backticks from a column so identifiers cannot break out of their quoting', function () {
    $sql = bigQueryTable()->orderBy('a` ) union all select * from `secret.data.rows` -- ')->toSql();

    expect($sql)->toBe('select * from `test-project.default_dataset.users` order by `a ) union all select * from secret`.`data`.`rows -- ` asc');
});

it('strips backticks from a where column', function () {
    $sql = bigQueryTable()->where('evil`,1) or (1=1 and `id', 1)->toSql();

    expect($sql)->toBe('select * from `test-project.default_dataset.users` where `evil,1) or (1=1 and id` = ?');
});

it('compiles json selectors with json_value', function () {
    expect(bigQueryTable()->where('meta->theme', 'dark')->toSql())
        ->toBe('select * from `test-project.default_dataset.users` where json_value(`meta`, \'$."theme"\') = ?');
});

it('compiles whereDay, whereMonth and whereYear with extract', function () {
    expect(bigQueryTable()->whereDay('created_at', 5)->toSql())
        ->toBe('select * from `test-project.default_dataset.users` where extract(day from `created_at`) = cast(? as int64)')
        ->and(bigQueryTable()->whereMonth('created_at', 5)->toSql())
        ->toBe('select * from `test-project.default_dataset.users` where extract(month from `created_at`) = cast(? as int64)')
        ->and(bigQueryTable()->whereYear('created_at', 2026)->toSql())
        ->toBe('select * from `test-project.default_dataset.users` where extract(year from `created_at`) = cast(? as int64)');
});

it('compiles whereDate and whereTime with a cast parameter', function () {
    expect(bigQueryTable()->whereDate('created_at', '2026-01-01')->toSql())
        ->toBe('select * from `test-project.default_dataset.users` where date(`created_at`) = cast(? as date)')
        ->and(bigQueryTable()->whereTime('created_at', '10:00:00')->toSql())
        ->toBe('select * from `test-project.default_dataset.users` where time(`created_at`) = cast(? as time)');
});

it('compiles inRandomOrder with rand', function () {
    expect(bigQueryTable()->inRandomOrder()->toSql())
        ->toBe('select * from `test-project.default_dataset.users` order by rand()');
});

it('adds the limit that BigQuery requires alongside a bare offset', function () {
    expect(bigQueryTable()->offset(10)->toSql())
        ->toBe('select * from `test-project.default_dataset.users` limit '.PHP_INT_MAX.' offset 10');
});

it('leaves an explicit limit in place when an offset is present', function () {
    expect(bigQueryTable()->offset(10)->limit(5)->toSql())
        ->toBe('select * from `test-project.default_dataset.users` limit 5 offset 10');
});

it('adds where true to an unfiltered delete', function () {
    $query = bigQueryTable();

    expect($query->getGrammar()->compileDelete($query))
        ->toBe('delete from `test-project.default_dataset.users` where true');
});

it('adds where true to an unfiltered update', function () {
    $query = bigQueryTable();

    expect($query->getGrammar()->compileUpdate($query, ['name' => 'x']))
        ->toBe('update `test-project.default_dataset.users` set `name` = ? where true');
});

it('refuses to compile an update with joins', function () {
    $query = bigQueryTable()->join('accounts', 'accounts.id', '=', 'users.account_id');

    $query->getGrammar()->compileUpdate($query, ['name' => 'x']);
})->throws(LogicException::class, 'BigQuery does not support joins in UPDATE statements');

it('refuses to compile a delete with joins', function () {
    $query = bigQueryTable()->join('accounts', 'accounts.id', '=', 'users.account_id');

    $query->getGrammar()->compileDelete($query);
})->throws(LogicException::class, 'BigQuery does not support joins in DELETE statements');

it('refuses to compile a row lock', function () {
    bigQueryTable()->lockForUpdate()->toSql();
})->throws(LogicException::class, 'BigQuery does not support row locking');

it('keeps working after another connection mutated the package config', function () {
    config()->set('bigquery-eloquent.project_id', 'fallback-project');
    config()->set('database.connections.bigquery_other', ['driver' => 'bigquery']);

    DB::connection('bigquery_other');

    expect(bigQueryTable()->toSql())
        ->toBe('select * from `test-project.default_dataset.users`');
});
