<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Database\Query\Grammars;

use Illuminate\Contracts\Database\Query\Expression;
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
        $table = ltrim($table, $prefix);

        if (stripos($table, ' as ') !== false) {
            [$name, $alias] = preg_split('/\s+as\s+/i', $table);

            return $this->wrapFullyQualified($name).' as '.$this->wrap($alias);
        }

        if (substr_count($table, '.') >= 2) {
            return $this->wrapFullyQualified($table);
        }

        return parent::wrapTable($table);
    }

    /**
     * Wrap a fully-qualified BigQuery identifier
     *
     * Why:
     * - BigQuery expects the entire string wrapped once in backticks.
     *   Example: `my-project.my_dataset.my_table`
     * - This prevents Laravel from splitting on "." incorrectly.
     */
    protected function wrapFullyQualified(string $name): string
    {
        $trim = trim($name, '`');

        return '`'.$trim.'`';
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

        $path = '$.'.str_replace('->', '.', $parts[1]);

        return "JSON_VALUE({$column}, '{$path}')";
    }
}
