<?php

namespace NomanSheikh\LaravelBigqueryEloquent;

use Closure;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryResults;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\Processor;
use LogicException;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryGrammar;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryProcessor;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryQueryBuilder;
use NomanSheikh\LaravelBigqueryEloquent\Schema\BigQuerySchemaBuilder;
use NomanSheikh\LaravelBigqueryEloquent\Schema\BigQuerySchemaGrammar;
use Override;

class BigQueryConnection extends Connection
{
    protected BigQueryClient $client;

    protected QueryResults $result;

    protected string $projectId;

    protected string $dataset;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->projectId = (string) ($config['project_id'] ?? '');
        $this->dataset = (string) ($config['dataset'] ?? '');

        $clientConfig = ['projectId' => $this->projectId];
        $keyFile = $config['key_file'] ?? null;

        if (is_array($keyFile)) {
            $clientConfig['keyFile'] = $keyFile;
        }

        if (is_string($keyFile) && $keyFile !== '') {
            $clientConfig['keyFilePath'] = $keyFile;
        }

        $this->client = new BigQueryClient($clientConfig);

        $this->database = $this->dataset;

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
    }

    public function getDefaultDataset(): string
    {
        return $this->dataset;
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getDatabaseName(): string
    {
        return $this->dataset;
    }

    public function getClient(): BigQueryClient
    {
        return $this->client;
    }

    #[Override]
    public function table($table, $as = null): BigQueryQueryBuilder
    {
        $query = new BigQueryQueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());

        return $query->from($table);
    }

    #[Override]
    protected function getDefaultQueryGrammar(): BigQueryGrammar
    {
        return new BigQueryGrammar($this);
    }

    #[Override]
    public function getSchemaBuilder(): BigQuerySchemaBuilder
    {
        return new BigQuerySchemaBuilder($this);
    }

    #[Override]
    protected function getDefaultPostProcessor(): Processor
    {
        return new BigQueryProcessor;
    }

    #[Override]
    protected function getDefaultSchemaGrammar(): BigQuerySchemaGrammar
    {
        return new BigQuerySchemaGrammar($this);
    }

    public function getDriverName(): string
    {
        return 'bigquery';
    }

    public function getDriverTitle(): string
    {
        return 'BigQuery';
    }

    public function getPdo(): never
    {
        throw new LogicException('BigQuery does not use PDO. Use getClient() to access the BigQuery client.');
    }

    public function getReadPdo(): never
    {
        throw new LogicException('BigQuery does not use PDO. Use getClient() to access the BigQuery client.');
    }

    #[Override]
    public function transaction(Closure $callback, $attempts = 1): never
    {
        throw new LogicException('BigQuery does not support transactions.');
    }

    #[Override]
    public function beginTransaction(): never
    {
        throw new LogicException('BigQuery does not support transactions.');
    }

    #[Override]
    public function commit(): never
    {
        throw new LogicException('BigQuery does not support transactions.');
    }

    #[Override]
    public function rollBack($toLevel = null): never
    {
        throw new LogicException('BigQuery does not support transactions.');
    }

    #[Override]
    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        $start = microtime(true);

        $job = $this->client->query($query);

        if (! empty($bindings)) {
            $job = $job->parameters($bindings);
        }

        $result = $this->client->runQuery($job);

        $this->logQuery($query, $bindings, $this->getElapsedTime($start));

        $rows = [];

        foreach ($result as $row) {
            $rows[] = (object) ((array) $row);
        }

        return $rows;
    }
}
