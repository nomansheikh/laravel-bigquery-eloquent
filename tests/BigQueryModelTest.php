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

    expect($sql)->toBe('select * from `test-project.default_dataset.test_jobs` where deleted_at is null');
});

it('generates where in syntax with bindings', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->whereIn('user_id', [1, 2])->toSql();

    expect($sql)->toBe('select * from `test-project.default_dataset.test_jobs` where user_id in (?, ?)');
});

it('generates JSON_VALUE for JSON path in where clause', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->where('payload->user->id', '=', 123)->toSql();

    expect($sql)->toBe("select * from `test-project.default_dataset.test_jobs` where JSON_VALUE(payload, '$.user.id') = ?");
});

it('generates is null for JSON path', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->whereNull('payload->user->id')->toSql();

    expect($sql)->toBe("select * from `test-project.default_dataset.test_jobs` where JSON_VALUE(payload, '$.user.id') is null");
});

it('applies limit and offset with forPage', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->forPage(2, 10)->toSql();

    expect($sql)->toBe('select * from `test-project.default_dataset.test_jobs` limit 10 offset 10');
});

it('generates is not null for JSON path', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->whereNotNull('payload->user->id')->toSql();

    expect($sql)->toBe("select * from `test-project.default_dataset.test_jobs` where JSON_VALUE(payload, '$.user.id') is not null");
});

it('can override dataset dynamically via setter', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $model->setDataset('runtime_ds');

    expect($model->getTable())->toBe('`test-project.runtime_ds.test_jobs`');
});
