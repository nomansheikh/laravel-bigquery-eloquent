<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Eloquent;

use Illuminate\Database\Eloquent\Builder;

class BigQueryBuilder extends Builder
{
    public function first($columns = ['*']): self
    {
        $this->limit(1)->get($columns);

        return $this;
    }

    public function update(array $values): void
    {
        $sql = $this->getGrammar()->compileUpdate($this->getQuery(), $values);
        $bindings = array_merge(array_values($values), $this->getQuery()->getBindings());

        $this->query->getConnection()->statement($sql, $bindings);
    }
}
