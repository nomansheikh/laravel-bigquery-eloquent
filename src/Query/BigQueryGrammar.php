<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Collection;
use LogicException;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use Override;

class BigQueryGrammar extends Grammar
{
    protected string $projectId;

    protected string $dataset;

    public function __construct(BigQueryConnection $connection)
    {
        parent::__construct($connection);

        $this->projectId = $connection->getProjectId();
        $this->dataset = $connection->getDefaultDataset();
    }

    #[Override]
    public function wrapTable($table, $prefix = null): string
    {
        if ($table instanceof Expression) {
            return (string) $this->getValue($table);
        }

        $segments = preg_split('/\s+as\s+/i', (string) $table, 2);

        if ($segments === false || count($segments) < 2) {
            return $this->wrapValue($this->qualifyTable((string) $table));
        }

        return "{$this->wrapTable($segments[0])} as {$this->wrapValue($segments[1])}";
    }

    protected function qualifyTable(string $table): string
    {
        if (str_contains($table, '.')) {
            return $table;
        }

        return "{$this->projectId}.{$this->dataset}.{$table}";
    }

    /**
     * BigQuery has no escape sequence for a backtick inside a quoted identifier,
     * so the delimiters are stripped rather than doubled.
     */
    #[Override]
    protected function wrapValue($value): string
    {
        if ($value === '*') {
            return $value;
        }

        return '`'.str_replace(['`', "\n", "\r"], '', (string) $value).'`';
    }

    /**
     * @param  array<int, string>  $segments
     */
    #[Override]
    protected function wrapSegments($segments): string
    {
        return (new Collection($segments))->map($this->wrapValue(...))->implode('.');
    }

    #[Override]
    protected function wrapJsonSelector($value): string
    {
        [$field, $path] = $this->wrapJsonFieldAndPath($value);

        return "json_value({$field}{$path})";
    }

    #[Override]
    public function getDateFormat(): string
    {
        return 'Y-m-d H:i:s.u';
    }

    #[Override]
    public function compileSelect(Builder $query): string
    {
        if ($query->offset !== null) {
            $query->limit ??= PHP_INT_MAX;
        }

        if ($query->unionOffset !== null) {
            $query->unionLimit ??= PHP_INT_MAX;
        }

        return parent::compileSelect($query);
    }

    #[Override]
    public function compileRandom($seed): string
    {
        return 'rand()';
    }

    /**
     * @param  array<string, mixed>  $where
     */
    #[Override]
    protected function whereDate(Builder $query, $where): string
    {
        return "date({$this->wrap($where['column'])}) {$where['operator']} cast({$this->parameter($where['value'])} as date)";
    }

    /**
     * @param  array<string, mixed>  $where
     */
    #[Override]
    protected function whereTime(Builder $query, $where): string
    {
        return "time({$this->wrap($where['column'])}) {$where['operator']} cast({$this->parameter($where['value'])} as time)";
    }

    /**
     * Laravel zero-pads day and month bindings into strings, so the parameter is cast
     * back to INT64 to match what EXTRACT returns.
     *
     * @param  array<string, mixed>  $where
     */
    #[Override]
    protected function dateBasedWhere($type, Builder $query, $where): string
    {
        return "extract({$type} from {$this->wrap($where['column'])}) {$where['operator']} cast({$this->parameter($where['value'])} as int64)";
    }

    #[Override]
    protected function compileUpdateWithoutJoins(Builder $query, $table, $columns, $where): string
    {
        return "update {$table} set {$columns} ".$this->requireWhere($where);
    }

    #[Override]
    protected function compileDeleteWithoutJoins(Builder $query, $table, $where): string
    {
        return "delete from {$table} ".$this->requireWhere($where);
    }

    /**
     * BigQuery rejects UPDATE and DELETE statements that have no WHERE clause.
     */
    protected function requireWhere(string $where): string
    {
        if ($where !== '') {
            return $where;
        }

        return 'where true';
    }

    #[Override]
    protected function compileUpdateWithJoins(Builder $query, $table, $columns, $where): never
    {
        throw new LogicException('BigQuery does not support joins in UPDATE statements. Write a MERGE statement and run it with DB::connection()->statement().');
    }

    #[Override]
    protected function compileDeleteWithJoins(Builder $query, $table, $where): never
    {
        throw new LogicException('BigQuery does not support joins in DELETE statements. Use a subquery in the WHERE clause instead.');
    }

    #[Override]
    protected function compileLock(Builder $query, $value): never
    {
        throw new LogicException('BigQuery does not support row locking.');
    }
}
