<?php

use NomanSheikh\LaravelBigqueryEloquent\Database\Query\Grammars\BigQueryGrammar;
use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

it('parameterizes scalar values to avoid injection', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->where('name', '=', "O'Reilly")->toSql();

    expect($sql)
        ->toContain(' where name = ?')
        ->and($sql)->not()->toContain("O'Reilly");
});

it('connection query grammar is our BigQueryGrammar', function () {
    $grammar = DB::connection('bigquery')->getQueryGrammar();

    expect($grammar)->toBeInstanceOf(BigQueryGrammar::class);
});

it('escapes quotes inside JSON path', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->where("payload->user'name", '=', 1)->toSql();

    expect($sql)
        ->toBe("select * from `test-project.default_dataset.test_jobs` where JSON_VALUE(payload, '$.user\'name') = ?");
});

it('backticks dangerous orderBy identifiers', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->orderBy('user_id; drop table users')->toSql();

    expect($sql)
        ->toBe('select * from `test-project.default_dataset.test_jobs` order by `user_id; drop table users` asc');
});

it('wraps table names with special characters to prevent injection', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'jobs;drop_table';
    };

    expect($model->getTable())
        ->toBe('`test-project.default_dataset.jobs;drop_table`');
});

it('does not inline IN list values', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->whereIn('name', ["x'; drop --", 'y'])->toSql();

    expect($sql)
        ->toContain(' where name in (?, ?)')
        ->and($sql)->not()->toContain('drop');
});
