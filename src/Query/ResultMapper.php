<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Query;

use DateTimeInterface;
use Google\Cloud\BigQuery\Bytes;
use Google\Cloud\BigQuery\ValueInterface;

class ResultMapper
{
    public function __construct(private readonly string $dateFormat) {}

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    public function mapRow(array $row): array
    {
        return array_map($this->mapValue(...), $row);
    }

    /**
     * Bytes::get() hands back a PSR-7 stream, so it is read out to the raw binary
     * string a BLOB column would produce on any other driver.
     */
    public function mapValue(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map($this->mapValue(...), $value);
        }

        if ($value instanceof Bytes) {
            return (string) $value->get();
        }

        if ($value instanceof ValueInterface) {
            return $value->formatAsString();
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format($this->dateFormat);
        }

        return $value;
    }
}
