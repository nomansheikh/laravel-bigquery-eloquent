<?php

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

    expect($wrapped)->toBe('my-project.my_dataset.my_table');
});

it('uses the BigQueryGrammar on the connection', function () {
    $connection = DB::connection('bigquery');

    expect($connection->getQueryGrammar())->toBeInstanceOf(BigQueryGrammar::class);
});
