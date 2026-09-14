<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Query;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Str;
use LogicException;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use Override;

/**
 * @property BigQueryConnection $connection
 */
class BigQueryQueryBuilder extends Builder
{
    #[Override]
    public function insertGetId(array $values, $sequence = null): never
    {
        throw new LogicException('BigQuery does not support auto-incrementing keys. Set $incrementing = false on your model and assign a key (e.g., ULID/UUID) before saving.');
    }

    #[Override]
    public function delete($id = null): int
    {
        if ($id !== null) {
            $this->where("{$this->tableReference()}.id", '=', $id);
        }

        return parent::delete();
    }

    /**
     * BigQuery gives an unaliased table the last segment of its path as an implicit
     * alias, which is what column references have to be qualified with.
     */
    protected function tableReference(): string
    {
        if ($this->from instanceof Expression) {
            return (string) $this->from->getValue($this->grammar);
        }

        $segments = preg_split('/\s+as\s+/i', (string) $this->from);

        if ($segments === false || $segments === []) {
            return Str::afterLast((string) $this->from, '.');
        }

        return Str::afterLast($segments[count($segments) - 1], '.');
    }
}
