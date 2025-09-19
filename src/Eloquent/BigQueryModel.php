<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use NomanSheikh\LaravelBigqueryEloquent\Query\BigQueryQueryBuilder;

abstract class BigQueryModel extends Model
{
    protected $connection = 'bigquery';

    public function getTable(): string
    {
        $table = $this->table ?: Str::snake(Str::pluralStudly(class_basename(static::class)));

        if (Str::contains($table, '.')) {
            return $table;
        }

        $project = $this->getConnection()->getProjectId();
        $dataset = $this->getConnection()->getDefaultDataset();

        return "$project.$dataset.$table";
    }

    public function newEloquentBuilder($query): BigQueryEloquentBuilder
    {
        return new BigQueryEloquentBuilder($query);
    }

    protected function newBaseQueryBuilder(): BigQueryQueryBuilder
    {
        return new BigQueryQueryBuilder(
            $this->getConnection(),
            $this->getConnection()->getQueryGrammar(),
            $this->getConnection()->getPostProcessor()
        );
    }
}
