<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

abstract class BigQueryModel extends Model
{
    protected $connection = 'bigquery';

    protected ?string $dataset = null;

    public function getTable(): string
    {
        $table = $this->table ?: Str::snake(Str::pluralStudly(class_basename(static::class)));

        $connName = $this->getConnectionName() ?: $this->connection;
        $dataset  = $this->dataset ?? config("database.connections.{$connName}.dataset");
        $project  = config("database.connections.{$connName}.project_id");

        return "{$project}.{$dataset}.{$table}";
    }

    public function setDataset(string $dataset): static
    {
        $this->dataset = $dataset;
        return $this;
    }
}
