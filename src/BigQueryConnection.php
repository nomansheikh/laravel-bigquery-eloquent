<?php

namespace NomanSheikh\LaravelBigqueryEloquent;

use Closure;
use DateTimeInterface;
use Generator;
use Google\Cloud\BigQuery\BigQueryClient;
use Google\Cloud\BigQuery\QueryJobConfiguration;
use Google\Cloud\BigQuery\QueryResults;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Processors\Processor;
use LogicException;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryGrammar;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryProcessor;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryQueryBuilder;
use NomanSheikh\LaravelBigqueryEloquent\Query\ResultMapper;
use NomanSheikh\LaravelBigqueryEloquent\Schema\BigQuerySchemaBuilder;
use NomanSheikh\LaravelBigqueryEloquent\Schema\BigQuerySchemaGrammar;
use Override;

class BigQueryConnection extends Connection
{
    protected BigQueryClient $client;

    protected string $projectId;

    protected string $dataset;

    protected ResultMapper $resultMapper;

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        $this->projectId = (string) ($config['project_id'] ?? '');
        $this->dataset = (string) ($config['dataset'] ?? '');

        $this->client = new BigQueryClient($this->buildClientConfig($config));

        $this->database = $this->dataset;

        $this->useDefaultQueryGrammar();
        $this->useDefaultPostProcessor();
        $this->useDefaultSchemaGrammar();

        $this->resultMapper = new ResultMapper($this->getQueryGrammar()->getDateFormat());
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function buildClientConfig(array $config): array
    {
        $clientConfig = ['projectId' => $this->projectId];

        $keyFile = $config['key_file'] ?? null;

        if (is_array($keyFile)) {
            $clientConfig['keyFile'] = $keyFile;
        }

        if (is_string($keyFile) && $keyFile !== '') {
            $clientConfig['keyFilePath'] = $keyFile;
        }

        if (! empty($config['location'])) {
            $clientConfig['location'] = $config['location'];
        }

        return $clientConfig;
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
    public function query(): BigQueryQueryBuilder
    {
        return new BigQueryQueryBuilder($this, $this->getQueryGrammar(), $this->getPostProcessor());
    }

    #[Override]
    public function table($table, $as = null): BigQueryQueryBuilder
    {
        return $this->query()->from($table, $as);
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

    /**
     * BigQuery jobs are stateless HTTP calls, so there is never a connection to restore.
     */
    #[Override]
    public function reconnectIfMissingConnection(): void {}

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

    /**
     * $fetchUsing drives PDO fetch modes on Laravel 13 and has no meaning here. It is
     * accepted so the signature satisfies both Laravel 12 and 13.
     *
     * @param  array<int, mixed>  $bindings
     * @param  array<mixed>  $fetchUsing
     * @return array<int, object>
     */
    #[Override]
    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): array
    {
        return $this->run($query, $bindings, function (string $query, array $bindings): array {
            if ($this->pretending()) {
                return [];
            }

            $rows = [];

            foreach ($this->runJob($query, $bindings)->rows() as $row) {
                $rows[] = (object) $this->resultMapper->mapRow((array) $row);
            }

            return $rows;
        });
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @param  array<mixed>  $fetchUsing
     * @return Generator<int, object>
     */
    #[Override]
    public function cursor($query, $bindings = [], $useReadPdo = true, array $fetchUsing = []): Generator
    {
        $results = $this->run($query, $bindings, function (string $query, array $bindings): ?QueryResults {
            if ($this->pretending()) {
                return null;
            }

            return $this->runJob($query, $bindings);
        });

        if ($results === null) {
            return;
        }

        foreach ($results->rows() as $row) {
            yield (object) $this->resultMapper->mapRow((array) $row);
        }
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    #[Override]
    public function statement($query, $bindings = []): bool
    {
        return $this->run($query, $bindings, function (string $query, array $bindings): bool {
            if ($this->pretending()) {
                return true;
            }

            $results = $this->runJob($query, $bindings);

            $this->recordsHaveBeenModified();

            return $results->isComplete();
        });
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    #[Override]
    public function affectingStatement($query, $bindings = []): int
    {
        return $this->run($query, $bindings, function (string $query, array $bindings): int {
            if ($this->pretending()) {
                return 0;
            }

            $affected = (int) ($this->runJob($query, $bindings)->info()['numDmlAffectedRows'] ?? 0);

            $this->recordsHaveBeenModified($affected > 0);

            return $affected;
        });
    }

    #[Override]
    public function unprepared($query): bool
    {
        return $this->statement($query);
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    protected function runJob(string $query, array $bindings): QueryResults
    {
        [$query, $bindings] = $this->inlineNullBindings($query, $this->prepareBindings($bindings));

        return $this->client->runQuery($this->newQueryJob($query, $bindings));
    }

    /**
     * @param  array<int, mixed>  $bindings
     */
    protected function newQueryJob(string $query, array $bindings): QueryJobConfiguration
    {
        $job = $this->client->query($query);

        if ($bindings !== []) {
            $job->parameters($bindings);
        }

        if (isset($this->config['maximum_bytes_billed'])) {
            $job->maximumBytesBilled((int) $this->config['maximum_bytes_billed']);
        }

        if (isset($this->config['job_timeout_ms'])) {
            $job->jobTimeoutMs((int) $this->config['job_timeout_ms']);
        }

        if (! empty($this->config['labels'])) {
            $job->labels($this->config['labels']);
        }

        return $job;
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<int, mixed>
     */
    #[Override]
    public function prepareBindings(array $bindings): array
    {
        return $this->normalizeBindings($bindings);
    }

    /**
     * @param  array<int, mixed>  $bindings
     * @return array<int, mixed>
     */
    public function normalizeBindings(array $bindings): array
    {
        return array_map(function (mixed $value): mixed {
            if ($value instanceof DateTimeInterface) {
                return $this->client->timestamp($value);
            }

            return $value;
        }, $bindings);
    }

    /**
     * BigQuery rejects untyped NULL query parameters, so null bindings are written
     * into the SQL as literals where the column type can be inferred instead.
     *
     * @param  array<int, mixed>  $bindings
     * @return array{0: string, 1: array<int, mixed>}
     */
    protected function inlineNullBindings(string $query, array $bindings): array
    {
        if (! array_is_list($bindings)) {
            return [$query, $bindings];
        }

        if (! in_array(null, $bindings, true)) {
            return [$query, $bindings];
        }

        $compiled = '';
        $remaining = [];
        $index = 0;
        $quote = null;
        $length = strlen($query);

        for ($position = 0; $position < $length; $position++) {
            $character = $query[$position];

            if ($quote !== null) {
                $compiled .= $character;

                if ($character === '\\') {
                    $compiled .= $query[++$position] ?? '';

                    continue;
                }

                if ($character === $quote) {
                    $quote = null;
                }

                continue;
            }

            if ($character === "'" || $character === '"' || $character === '`') {
                $quote = $character;
                $compiled .= $character;

                continue;
            }

            if ($character !== '?' || ! array_key_exists($index, $bindings)) {
                $compiled .= $character;

                continue;
            }

            $value = $bindings[$index++];

            if ($value === null) {
                $compiled .= 'null';

                continue;
            }

            $remaining[] = $value;
            $compiled .= '?';
        }

        return [$compiled, array_merge($remaining, array_slice($bindings, $index))];
    }
}
