<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Query;

use Illuminate\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\Grammar;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;

/**
 * @property BigQueryConnection $connection
 */
class BigQueryGrammar extends Grammar
{
    public function wrapTable($table, $prefix = null): string
    {
        if ($table instanceof Expression) {
            return $this->getValue($table);
        }

        if (str_starts_with($table, '`') && str_ends_with($table, '`')) {
            return $table;
        }

        if (stripos($table, ' as ') !== false) {
            [$name, $alias] = preg_split('/\s+as\s+/i', $table);

            return "`$name` as $alias";
        }

        if (str_contains($table, '.')) {
            return "`$table`";
        }

        $project = $this->connection->getProjectId();
        $dataset = $this->connection->getDefaultDataset();

        return "`$project.$dataset.$table`";
    }

    public function wrap($value)
    {
        if ($value === '*' || str_contains($value, '.') || str_contains($value, '`')) {
            return $value;
        }

        // Wrap normal columns, including timestamps, without table prefix
        return "`$value`";
    }
}
