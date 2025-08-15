<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Database\Query\Grammars;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Grammars\MySqlGrammar;

class BigQueryGrammar extends MySqlGrammar
{
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

    protected function wrapFullyQualified(string $name): string
    {
        $trim = trim($name, '`');

        return '`'.$trim.'`';
    }
}
