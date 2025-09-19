<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Eloquent;

use Illuminate\Database\Eloquent\Builder;

class BigQueryEloquentBuilder extends Builder
{
    public function update(array $values): int
    {
        return $this->toBase()->update($this->addUpdatedAtColumn($values));
    }

    protected function addUpdatedAtColumn(array $values): array
    {
        return $values;
    }
}
