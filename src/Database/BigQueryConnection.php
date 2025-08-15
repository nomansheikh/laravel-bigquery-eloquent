<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Database;

use Google\Cloud\BigQuery\BigQueryClient;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\Processor;
use NomanSheikh\LaravelBigqueryEloquent\Database\Query\Grammars\BigQueryGrammar;
use RuntimeException;

class BigQueryConnection extends Connection
{
    protected BigQueryClient $client;

    protected string $projectId;

    protected string $dataset;

    public function __construct(array $config)
    {
        $this->projectId = (string) ($config['project_id'] ?? '');
        $this->dataset = (string) ($config['dataset'] ?? '');

        $this->client = new BigQueryClient([
            'projectId' => $this->projectId,
            'keyFilePath' => (string) ($config['key_file'] ?? ''),
        ]);

        $pdoResolver = function () {};
        parent::__construct($pdoResolver, '', '', $config);
    }

    public function getClient(): BigQueryClient
    {
        return $this->client;
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getDefaultDataset(): string
    {
        return $this->dataset;
    }

    protected function getDefaultQueryGrammar(): BigQueryGrammar
    {
        return new BigQueryGrammar($this);
    }

    protected function getDefaultPostProcessor(): Processor
    {
        return new Processor;
    }

    public function select($query, $bindings = [], $useReadPdo = true): array
    {
        $this->logQuery($query, $bindings);

        $job = $this->client->query($query)->parameters(array_values($bindings));
        $result = $this->client->runQuery($job);

        $rows = [];
        foreach ($result as $row) {
            $rows[] = (array) $row;
        }

        return $rows;
    }

    public function affectingStatement($query, $bindings = [])
    {
        throw new RuntimeException('BigQuery driver (simple mode) does not support write statements yet.');
    }
}
