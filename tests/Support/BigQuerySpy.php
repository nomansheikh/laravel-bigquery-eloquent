<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Tests\Support;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
use Google\Cloud\BigQuery\Timestamp;
use Mockery;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use ReflectionProperty;
use Throwable;

class BigQuerySpy
{
    public ?string $sql = null;

    /**
     * @var array<int, mixed>
     */
    public array $parameters = [];

    public bool $ranQuery = false;

    /**
     * @var array<string, mixed>
     */
    public array $jobOptions = [];

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @param  array<string, mixed>  $info
     */
    public static function attach(BigQueryConnection $connection, array $rows = [], array $info = [], ?Throwable $failWith = null): self
    {
        $spy = new self;

        $config = Mockery::mock(QueryJobConfiguration::class);
        $config->shouldReceive('parameters')->andReturnUsing(function (array $parameters) use ($config, $spy) {
            $spy->parameters = $parameters;

            return $config;
        });

        foreach (['maximumBytesBilled', 'jobTimeoutMs', 'labels'] as $option) {
            $config->shouldReceive($option)->andReturnUsing(function (mixed $value) use ($config, $spy, $option) {
                $spy->jobOptions[$option] = $value;

                return $config;
            });
        }

        $results = Mockery::mock(QueryResults::class);
        $results->shouldReceive('info')->andReturn($info);
        $results->shouldReceive('rows')->andReturn(new \ArrayIterator($rows));
        $results->shouldReceive('isComplete')->andReturnTrue();

        $client = Mockery::mock(BigQueryClient::class);
        $client->shouldReceive('query')->andReturnUsing(function (string $sql) use ($config, $spy, $failWith) {
            if ($failWith !== null) {
                throw $failWith;
            }

            $spy->sql = $sql;

            return $config;
        });
        $client->shouldReceive('runQuery')->andReturnUsing(function () use ($results, $spy) {
            $spy->ranQuery = true;

            return $results;
        });
        $client->shouldReceive('timestamp')->andReturnUsing(fn ($value) => new Timestamp($value));

        (new ReflectionProperty($connection, 'client'))->setValue($connection, $client);

        return $spy;
    }
}
