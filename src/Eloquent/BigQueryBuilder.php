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

    public function update(array $values): true
    {
        $sql = $this->getGrammar()->compileUpdate($this->getQuery(), $values);
        $bindings = array_merge(array_values($values), $this->getQuery()->getBindings());

        $this->query->getConnection()->statement($sql, $bindings);

        return true;
    }

    public function delete(): bool
    {
        $sql = $this->getGrammar()->compileDelete($this->getQuery());
        $bindings = $this->getQuery()->getBindings();

        $this->query->getConnection()->statement($sql, $bindings);

        return true;
    }
}
