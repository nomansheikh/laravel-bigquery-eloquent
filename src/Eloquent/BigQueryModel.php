<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use LogicException;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryQueryBuilder;
use Override;

abstract class BigQueryModel extends Model
{
    protected $connection = 'bigquery';

    public $incrementing = false;

    protected $keyType = 'string';

    #[Override]
    public function getTable(): string
    {
        $table = $this->table ?: Str::snake(Str::pluralStudly(class_basename(static::class)));

        if (str_contains($table, '.')) {
            return $table;
        }

        $connection = $this->bigQueryConnection();

        return "{$connection->getProjectId()}.{$connection->getDefaultDataset()}.{$table}";
    }

    /**
     * A fully qualified table is referenced by the last segment of its path, so columns
     * are qualified with that rather than with the whole `project.dataset.table` string.
     */
    #[Override]
    public function qualifyColumn($column): string
    {
        if (str_contains($column, '.')) {
            return $column;
        }

        return "{$this->getTableReference()}.{$column}";
    }

    public function getTableReference(): string
    {
        return Str::afterLast($this->getTable(), '.');
    }

    /**
     * @return BigQueryEloquentBuilder<*>
     */
    #[Override]
    public function newEloquentBuilder($query): BigQueryEloquentBuilder
    {
        return new BigQueryEloquentBuilder($query);
    }

    #[Override]
    protected function newBaseQueryBuilder(): BigQueryQueryBuilder
    {
        return $this->bigQueryConnection()->query();
    }

    protected function bigQueryConnection(): BigQueryConnection
    {
        $connection = $this->getConnection();

        if (! $connection instanceof BigQueryConnection) {
            throw new LogicException(static::class." is configured to use the [{$this->getConnectionName()}] connection, which is not a BigQuery connection.");
        }

        return $connection;
    }
}
