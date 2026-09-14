<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Eloquent;

use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Override;

/**
 * @template TModel of Model
 *
 * @extends Builder<TModel>
 */
class BigQueryEloquentBuilder extends Builder
{
    /**
     * Laravel selects `{$from}.*` here, and for BigQuery `$from` is a full
     * `project.dataset.table` path rather than something a column can be prefixed with.
     *
     * @param  mixed  $relations
     * @param  Expression|string  $column
     */
    #[Override]
    public function withAggregate($relations, $column, $function = null): static
    {
        if (! empty($relations)) {
            if ($this->query->columns === null) {
                $this->query->select([$this->model->qualifyColumn('*')]);
            }
        }

        return parent::withAggregate($relations, $column, $function);
    }

    /**
     * Laravel qualifies the "updated at" column with the raw `from` clause, which for
     * BigQuery is a full `project.dataset.table` path and is not a valid column prefix.
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    #[Override]
    protected function addUpdatedAtColumn(array $values): array
    {
        $values = parent::addUpdatedAtColumn($values);

        $column = $this->model->getUpdatedAtColumn();

        if ($column === null) {
            return $values;
        }

        $from = $this->query->from;

        if ($from instanceof Expression) {
            return $values;
        }

        $segments = preg_split('/\s+as\s+/i', $from) ?: [$from];

        $qualified = $segments[count($segments) - 1].'.'.$column;
        $reference = $this->model->qualifyColumn($column);

        if ($qualified === $reference) {
            return $values;
        }

        if (! array_key_exists($qualified, $values)) {
            return $values;
        }

        $values[$reference] = $values[$qualified];

        unset($values[$qualified]);

        return $values;
    }
}
