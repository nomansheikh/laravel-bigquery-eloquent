<?php

use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

it('infers table name from model', function () {
    $model = new class extends BigQueryModel {};

    $reflection = new ReflectionClass($model);
    $property = $reflection->getProperty('table');
    $property->setValue($model, 'test_jobs');

    expect($model->getTable())->toBe('test-project.default_dataset.test_jobs');
});

it('allows table override', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'custom_jobs';
    };

    expect($model->getTable())->toBe('test-project.default_dataset.custom_jobs');
});

it('generates correct sql for BigQuery', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $query = $model->where('salary', '>', 100000)
        ->from($model->getTable(),  't')
        ->select(['t.user_id', 't.salary', 't.job_title'])
        ->toSql();

    expect($query)
        ->toBe('select t.user_id, t.salary, t.job_title from `test-project.default_dataset.test_jobs` as t where `salary` > ?');
});

it('model can be used with alias in from', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->from($model->getTable().' as j')->select('j.user_id')->toSql();

    expect($sql)->toBe('select j.user_id from `test-project.default_dataset.test_jobs` as j');
});

it('generates where null syntax', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->whereNull('deleted_at')->toSql();

    expect($sql)->toBe('select * from `test-project.default_dataset.test_jobs` where `deleted_at` is null');
});

it('generates where in syntax with bindings', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->whereIn('user_id', [1, 2])->toSql();

    expect($sql)->toBe('select * from `test-project.default_dataset.test_jobs` where `user_id` in (?, ?)');
});

it('applies limit and offset with forPage', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->forPage(2, 10)->toSql();

    expect($sql)->toBe('select * from `test-project.default_dataset.test_jobs` limit 10 offset 10');
});
