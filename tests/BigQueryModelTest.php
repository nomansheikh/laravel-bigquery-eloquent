<?php

use Illuminate\Database\Eloquent\Relations\HasMany;
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
        ->from($model->getTable(), 't')
        ->select(['t.user_id', 't.salary', 't.job_title'])
        ->toSql();

    expect($query)
        ->toBe('select `t`.`user_id`, `t`.`salary`, `t`.`job_title` from `test-project.default_dataset.test_jobs` as `t` where `salary` > ?');
});

it('model can be used with alias in from', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $sql = $model->from($model->getTable().' as j')->select('j.user_id')->toSql();

    expect($sql)->toBe('select `j`.`user_id` from `test-project.default_dataset.test_jobs` as `j`');
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

it('qualifies columns with the table reference rather than the full path', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    expect($model->qualifyColumn('id'))->toBe('test_jobs.id')
        ->and($model->getQualifiedKeyName())->toBe('test_jobs.id');
});

it('generates a valid lookup by key', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    expect($model->whereKey(1)->toSql())
        ->toBe('select * from `test-project.default_dataset.test_jobs` where `test_jobs`.`id` = ?');
});

it('generates a valid relation constraint', function () {
    $parent = new AuditParent;
    $parent->id = 1;

    expect($parent->children()->toSql())
        ->toBe('select * from `test-project.default_dataset.test_jobs` where `test_jobs`.`parent_id` = ? and `test_jobs`.`parent_id` is not null');
});

it('defaults to non incrementing string keys', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    expect($model->getIncrementing())->toBeFalse()
        ->and($model->getKeyType())->toBe('string');
});

it('refuses to build a query on a non BigQuery connection', function () {
    config()->set('database.connections.sqlite_probe', [
        'driver' => 'sqlite',
        'database' => ':memory:',
    ]);

    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';

        protected $connection = 'sqlite_probe';
    };

    $model->getTable();
})->throws(LogicException::class, 'is not a BigQuery connection');

it('refuses insertGetId because BigQuery has no auto increment', function () {
    $model = new class extends BigQueryModel
    {
        protected $table = 'test_jobs';
    };

    $model->newQuery()->getQuery()->insertGetId(['name' => 'foo']);
})->throws(LogicException::class, 'BigQuery does not support auto-incrementing keys');

class AuditParent extends BigQueryModel
{
    protected $table = 'test_jobs';

    public function children(): HasMany
    {
        return $this->hasMany(AuditParent::class, 'parent_id', 'id');
    }
}
