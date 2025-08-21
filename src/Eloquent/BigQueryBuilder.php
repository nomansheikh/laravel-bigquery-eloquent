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
}
