<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Eloquent;

use Illuminate\Database\Eloquent\Builder;
use NomanSheikh\LaravelBigqueryEloquent\Database\BigQueryConnection;

class BigQueryBuilder extends Builder
{
    public function first($columns = ['*']): self // @phpstan-ignore-line
    {
        $this->limit(1)->get($columns);

        return $this;
    }

    public function update(array $values): int
    {
        $sql = $this->getGrammar()->compileUpdate($this->getQuery(), $values);
        $bindings = array_merge(array_values($values), $this->getQuery()->getBindings());

        $this->query->getConnection()->statement($sql, $bindings);

        /** @var BigQueryConnection $connection */
        $connection = $this->query->getConnection();

        return $connection->getAffectedRows();
    }

    public function delete(): bool
    {
        $sql = $this->getGrammar()->compileDelete($this->getQuery());
        $bindings = $this->getQuery()->getBindings();

        $this->query->getConnection()->statement($sql, $bindings);

        return true;
    }
}
