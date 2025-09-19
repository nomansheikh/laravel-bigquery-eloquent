<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Query;

use Illuminate\Database\Query\Grammars\Grammar;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;

/**
 * @property BigQueryConnection $connection
 */
class BigQueryGrammar extends Grammar
{
    public function wrapTable($table, $prefix = null): string
    {
        if (str_starts_with($table, '`') && str_ends_with($table, '`')) {
            return $table;
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
        // Leave * as-is
        if ($value === '*') {
            return $value;
        }

        // Leave fully qualified tables alone
        if (str_contains($value, '.') && ! in_array($value, ['created_at', 'updated_at'])) {
            return collect(explode('.', $value))
                ->map(fn ($v) => "`$v`")
                ->implode('.');
        }

        // Wrap normal columns, including timestamps, without table prefix
        return "`$value`";
    }
}
