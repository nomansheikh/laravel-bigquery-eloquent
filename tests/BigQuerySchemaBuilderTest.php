<?php

use Illuminate\Support\Facades\Schema;
use NomanSheikh\LaravelBigqueryEloquent\Schema\BigQuerySchemaBuilder;

it('exposes a BigQuery schema builder', function () {
    expect(Schema::connection('bigquery'))->toBeInstanceOf(BigQuerySchemaBuilder::class);
});

it('explains that hasTable is not implemented instead of crashing', function () {
    Schema::connection('bigquery')->hasTable('users');
})->throws(LogicException::class, 'Schema::hasTable() is not implemented for the BigQuery driver');

it('explains that getColumnListing is not implemented', function () {
    Schema::connection('bigquery')->getColumnListing('users');
})->throws(LogicException::class, 'Schema::getColumnListing() is not implemented for the BigQuery driver');

it('explains that create is not implemented', function () {
    Schema::connection('bigquery')->create('users', fn () => null);
})->throws(LogicException::class, 'Schema::create() is not implemented for the BigQuery driver');
