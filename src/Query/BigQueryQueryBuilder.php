<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Query;

use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryResults;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Database\Query\Processors\Processor;
use Illuminate\Support\Collection;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;

/**
 * @property BigQueryConnection $connection
 */
class BigQueryQueryBuilder extends Builder
{
    protected BigQueryClient $client;

    /**
     * @param  BigQueryConnection  $connection
     */
    public function __construct(ConnectionInterface $connection, ?Grammar $grammar = null, ?Processor $processor = null)
    {
        parent::__construct($connection, $grammar, $processor);

        $this->client = $connection->getClient();
    }

    public function get($columns = ['*']): Collection
    {
        $sql = $this->toSql();
        $bindings = $this->getBindings();

        $result = $this->runQuery($sql, $bindings);

        return collect($result->rows())->map(fn ($row) => (array) $row);
    }

    public function update(array $values): bool
    {
        $sql = $this->grammar->compileUpdate($this, $values);
        $bindings = array_merge(array_values($values), $this->getBindings());

        $result = $this->runQuery($sql, $bindings);

        return $result->isComplete();
    }

    public function delete($id = null): int
    {
        $sql = $this->grammar->compileDelete($this);
        $bindings = $this->getBindings();

        $result = $this->runQuery($sql, $bindings);

        return $result->isComplete();
    }

    private function getElapsedTime($start): float
    {
        return round((microtime(true) - $start) * 1000, 2);
    }

    private function runQuery(string $sql, array $bindings): QueryResults
    {
        $start = microtime(true);

        $job = $this->client->query($sql)->parameters($bindings);

        $result = $this->client->runQuery($job);

        $this->connection->logQuery($sql, $bindings, $this->getElapsedTime($start));

        return $result;
    }
}
