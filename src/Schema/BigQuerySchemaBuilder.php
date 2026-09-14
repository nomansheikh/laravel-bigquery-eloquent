<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Schema;

use Closure;
use Illuminate\Database\Schema\Builder;
use LogicException;
use Override;

class BigQuerySchemaBuilder extends Builder
{
    #[Override]
    public function hasTable($table): never
    {
        $this->unsupported('hasTable');
    }

    #[Override]
    public function hasColumn($table, $column): never
    {
        $this->unsupported('hasColumn');
    }

    #[Override]
    public function hasColumns($table, $columns): never
    {
        $this->unsupported('hasColumns');
    }

    #[Override]
    public function getTables($schema = null): never
    {
        $this->unsupported('getTables');
    }

    #[Override]
    public function getColumns($table): never
    {
        $this->unsupported('getColumns');
    }

    #[Override]
    public function getColumnListing($table): never
    {
        $this->unsupported('getColumnListing');
    }

    #[Override]
    public function create($table, Closure $callback): never
    {
        $this->unsupported('create');
    }

    #[Override]
    public function table($table, Closure $callback): never
    {
        $this->unsupported('table');
    }

    #[Override]
    public function drop($table): never
    {
        $this->unsupported('drop');
    }

    #[Override]
    public function dropIfExists($table): never
    {
        $this->unsupported('dropIfExists');
    }

    #[Override]
    public function rename($from, $to): never
    {
        $this->unsupported('rename');
    }

    protected function unsupported(string $method): never
    {
        throw new LogicException("Schema::{$method}() is not implemented for the BigQuery driver. Run the statement yourself with DB::connection()->statement(), or use the BigQuery client via DB::connection()->getClient().");
    }
}
