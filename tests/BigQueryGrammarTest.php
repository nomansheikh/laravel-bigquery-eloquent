<?php

it('wraps JSON selectors using JSON_VALUE for scalars', function () {
    $connection = $this->app['db']->connection('bigquery');
    $grammar = $connection->getQueryGrammar();

    // Reflection to access protected wrapJsonSelector
    $ref = new ReflectionClass($grammar);
    $method = $ref->getMethod('wrapJsonSelector');

    // Single-level JSON path
    $sql = $method->invoke($grammar, 'payload->user_id');
    expect($sql)->toBe("JSON_VALUE(payload, '$.user_id')");

    // Nested JSON path
    $sqlNested = $method->invoke($grammar, 'payload->user->id');
    expect($sqlNested)->toBe("JSON_VALUE(payload, '$.user.id')");

    // Column without JSON path
    $sqlColumn = $method->invoke($grammar, 'payload');
    expect($sqlColumn)->toBe('payload');
});

it('qualifies table alias correctly without over-wrapping', function () {
    // Ensure we use the BigQuery connection builder
    $sql = $this->app['db']->connection('bigquery')->query()
        ->from('project.ds.jobs as t')
        ->select('t.id')
        ->toSql();

    expect($sql)->toBe('select t.id from project.ds.jobs as t');
});

it('does not over-wrap dotted identifiers in wrap', function () {
    $connection = $this->app['db']->connection('bigquery');
    $grammar = $connection->getQueryGrammar();

    $wrapped = $grammar->wrap('t.column');

    expect($wrapped)->toBe('t.column');
});

it('wrapTable backticks simple table names', function () {
    $connection = $this->app['db']->connection('bigquery');
    $grammar = $connection->getQueryGrammar();

    $ref = new ReflectionClass($grammar);
    $method = $ref->getMethod('wrapTable');

    $wrapped = $method->invoke($grammar, 'jobs');

    expect($wrapped)->toBe('`jobs`');
});
