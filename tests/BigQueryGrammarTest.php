<?php

it('wraps JSON selectors using JSON_VALUE for scalars', function () {
    $connection = $this->app['db']->connection('bigquery');
    $grammar = $connection->getQueryGrammar();

    // Reflection to access protected wrapJsonSelector
    $ref = new ReflectionClass($grammar);
    $method = $ref->getMethod('wrapJsonSelector');

    // Single-level JSON path
    $sql = $method->invoke($grammar, 'payload->user_id');
    expect($sql)->toBe("JSON_VALUE(`payload`, '$.user_id')");

    // Nested JSON path
    $sqlNested = $method->invoke($grammar, 'payload->user->id');
    expect($sqlNested)->toBe("JSON_VALUE(`payload`, '$.user.id')");

    // Column without JSON path
    $sqlColumn = $method->invoke($grammar, 'payload');
    expect($sqlColumn)->toBe('`payload`');
});
