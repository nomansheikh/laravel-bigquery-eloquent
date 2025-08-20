<?php

use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

it('infers table name from model', function () {
    $model = new class extends BigQueryModel {};

    $reflection = new ReflectionClass($model);
    $property = $reflection->getProperty('table');
    $property->setValue($model, 'test_jobs');

    expect($model->getTable())->toBe('`test-project.default_dataset.test_jobs`');
});

it('allows table override', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'custom_jobs';
    };

    expect($model->getTable())->toBe('`test-project.default_dataset.custom_jobs`');
});

it('allows dataset override', function () {
    $model = new class extends BigQueryModel
    {
        protected ?string $dataset = 'another_dataset';

        protected $table = 'special_jobs';
    };

    expect($model->getTable())->toBe('`test-project.another_dataset.special_jobs`');
});

it('generates correct sql for BigQuery', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $query = $model->where('salary', '>', 100000)
        ->from($model->getTable().' as t')
        ->select(['t.user_id', 't.salary', 't.job_title'])
        ->toSql();

    expect($query)
        ->toBe('select t.user_id, t.salary, t.job_title from `test-project.default_dataset.test_jobs` as t where salary > ?');
});
