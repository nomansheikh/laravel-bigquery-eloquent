<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Database\Query\Grammars;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

class BigQueryGrammar extends MySqlGrammar
{
    /**
     * Wrap a table name for BigQuery.
     *
     * Why:
     * - BigQuery requires FULLY qualified names (project.dataset.table) to be wrapped as
     *   one identifier:  `project.dataset.table`
     * - If we let the default grammar handle it, Laravel would split them like:
     *     `project`.`dataset`.`table`  (invalid in BigQuery)
     *
     * - This method also ensures aliases still work: "table as t"
     */
    public function wrapTable($table, $prefix = null): float|int|string|Expression
    {
        if ($table instanceof Expression) {
            return $this->getValue($table);
        }

        // Skip wrapping if already fully qualified
        if (str_contains($table, '.')) {
            return $table;
        }

        return "`{$table}`";
    }

    protected function wrapValue($value): string
    {
        // Don't wrap the wildcard
        if ($value === '*') {
            return $value;
        }

        // Do not backtick aliases or normal identifiers
        // unless they contain special chars (like - or space)
        if (preg_match('/[^A-Za-z0-9_]/', $value)) {
            return "`{$value}`";
        }

        return $value;
    }

    public function wrap($value, $prefixAlias = false): float|int|string|Expression
    {
        if ($value instanceof Expression) {
            return $this->getValue($value);
        }

        // Handle JSON selectors like "column->path"
        if (is_string($value) && str_contains($value, '->')) {
            return $this->wrapJsonSelector($value);
        }

        // Example: "alias.column" → leave as-is, don’t turn into `alias`.`column`
        if (str_contains($value, '.')) {
            return $value;
        }

        return $this->wrapValue($value);
    }

    protected function whereNull(Builder $query, $where): string
    {
        $column = $where['column'];

        if (is_string($column) && str_contains($column, '->')) {
            return $this->wrapJsonSelector($column).' is null';
        }

        return parent::whereNull($query, $where);
    }

    protected function whereNotNull(Builder $query, $where): string
    {
        $column = $where['column'];

        if (is_string($column) && str_contains($column, '->')) {
            return $this->wrapJsonSelector($column).' is not null';
        }

        return parent::whereNotNull($query, $where);
    }

    protected function whereJsonNull(Builder $query, $where): string
    {
        return $this->wrapJsonSelector($where['column']).' is null';
    }

    protected function whereJsonNotNull(Builder $query, $where): string
    {
        return $this->wrapJsonSelector($where['column']).' is not null';
    }

    /**
     * Wrap JSON selector for BigQuery.
     *
     * Why:
     * - In Laravel/MySQL, "col->path" becomes json_extract(col, '$.path')
     * - In BigQuery, we prefer JSON_VALUE(col, '$.path') for scalars.
     *
     * Example:
     *   Eloquent: where('payload->user->id', '=', 123)
     *   SQL: where JSON_VALUE(`payload`, '$.user.id') = 123
     */
    protected function wrapJsonSelector($value): float|int|string|Expression
    {
        // Split "column->path1->path2"
        $parts = explode('->', $value, 2);

        $column = $this->wrap($parts[0]);

        if (count($parts) === 1) {
            return $column; // no JSON path, just column
        }

        $rawPath = str_replace('->', '.', $parts[1]);
        // Escape single quotes in JSON path to avoid breaking the string literal
        $escapedPath = str_replace("'", "\\'", $rawPath);
        $path = '$.'.$escapedPath;

        return "JSON_VALUE({$column}, '{$path}')";
    }
}
