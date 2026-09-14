<?php

/*
 * Live smoke test against a real BigQuery project.
 *
 * Creates a throwaway dataset, exercises the driver against it, and drops it again.
 * Nothing outside that dataset is written to.
 *
 *   BIGQUERY_SMOKE_PROJECT=your-project php tests/smoke.php
 *
 * The table is populated with CREATE TABLE AS SELECT rather than INSERT, so the read
 * checks also run on a BigQuery sandbox project, which does not support DML. The write
 * checks are skipped there rather than reported as failures.
 *
 * Optional: BIGQUERY_SMOKE_LOCATION (default US), BIGQUERY_SMOKE_KEEP=1 to skip cleanup.
 */

use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use NomanSheikh\LaravelBigqueryEloquent\BigQueryConnection;
use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

require __DIR__.'/../vendor/autoload.php';

$project = getenv('BIGQUERY_SMOKE_PROJECT');
$location = getenv('BIGQUERY_SMOKE_LOCATION') ?: 'US';

if (! $project) {
    fwrite(STDERR, "BIGQUERY_SMOKE_PROJECT is not set.\n");
    exit(1);
}

$dataset = 'laravel_bq_smoke_'.substr(bin2hex(random_bytes(4)), 0, 8);

$connection = new BigQueryConnection([
    'driver' => 'bigquery',
    'project_id' => $project,
    'dataset' => $dataset,
    'location' => $location,
    'maximum_bytes_billed' => 1024 * 1024 * 1024,
]);

$resolver = new ConnectionResolver(['bigquery' => $connection]);
$resolver->setDefaultConnection('bigquery');
Model::setConnectionResolver($resolver);

class SmokeEvent extends BigQueryModel
{
    protected $table = 'smoke_events';

    protected $guarded = [];

    protected $casts = ['occurred_at' => 'datetime'];

    public function children()
    {
        return $this->hasMany(self::class, 'parent_id', 'id');
    }
}

$passed = 0;
$skipped = 0;
$failed = [];

$check = function (string $name, callable $assertion) use (&$passed, &$failed): void {
    try {
        $detail = $assertion();
        $passed++;
        printf("  \033[32mPASS\033[0m  %s%s\n", $name, $detail ? "  \033[90m{$detail}\033[0m" : '');
    } catch (Throwable $exception) {
        $message = preg_replace('/\s+/', ' ', $exception->getMessage());
        $failed[] = [$name, $message];
        printf("  \033[31mFAIL\033[0m  %s\n        %s\n", $name, substr($message, 0, 260));
    }
};

$skip = function (string $name, string $reason) use (&$skipped): void {
    $skipped++;
    printf("  \033[33mSKIP\033[0m  %s  \033[90m%s\033[0m\n", $name, $reason);
};

$expect = function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};

echo "\nProject:  {$project}\nDataset:  {$dataset} (created, then dropped)\nLocation: {$location}\n\n";

$client = $connection->getClient();
$client->createDataset($dataset, ['location' => $location]);

$table = "{$project}.{$dataset}.smoke_events";

try {
    echo "Setup\n";

    $check('statement() runs DDL and populates via CTAS', function () use ($connection, $table) {
        $connection->statement("CREATE TABLE `{$table}` AS
            SELECT 'evt_1' AS id, 'signup' AS name, 10 AS visits, 1.5 AS ratio,
                   NUMERIC '1.50' AS amount,
                   TIMESTAMP '2026-03-04 05:06:07.891011+00' AS occurred_at,
                   DATE '2026-01-02' AS occurred_on,
                   JSON '{\"theme\":\"dark\"}' AS payload,
                   b'abc' AS raw,
                   CAST(NULL AS STRING) AS parent_id,
                   CURRENT_TIMESTAMP() AS created_at, CURRENT_TIMESTAMP() AS updated_at
            UNION ALL SELECT 'evt_2', 'purchase', 3, 0.5, NUMERIC '2.25',
                   TIMESTAMP '2026-05-06 01:02:03.456789+00', DATE '2026-02-03',
                   JSON '{\"theme\":\"light\"}', b'def', 'evt_1',
                   CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()
            UNION ALL SELECT 'evt_3', NULL, 7, 2.5, NUMERIC '3.75',
                   TIMESTAMP '2026-07-08 09:09:09.000000+00', DATE '2026-03-04',
                   JSON '{\"theme\":\"dark\"}', b'ghi', 'evt_1',
                   CURRENT_TIMESTAMP(), CURRENT_TIMESTAMP()");

        return '3 rows';
    });

    $dmlAvailable = false;

    try {
        $connection->affectingStatement("UPDATE `{$table}` SET name = name WHERE FALSE");
        $dmlAvailable = true;
    } catch (Throwable $exception) {
        $dmlReason = str_contains($exception->getMessage(), 'billing')
            ? 'sandbox project: DML needs billing enabled'
            : 'DML unavailable: '.substr(preg_replace('/\s+/', ' ', $exception->getMessage()), 0, 80);
    }

    echo "\nReads (the implicit-alias assumption)\n";

    $check('Model::find() resolves the qualified key', function () use ($expect) {
        $event = SmokeEvent::find('evt_1');

        $expect($event !== null, 'find() returned null');
        $expect($event->name === 'signup', 'unexpected name: '.var_export($event->name, true));

        return 'where `smoke_events`.`id` = ?';
    });

    $check('relation constraint resolves', function () use ($expect) {
        $count = SmokeEvent::find('evt_1')->children()->count();

        $expect($count === 2, "expected 2 children, got {$count}");

        return null;
    });

    $check('withCount() selects the table reference', function () use ($expect) {
        $event = SmokeEvent::withCount('children')->where('id', 'evt_1')->first();

        $expect((int) $event->children_count === 2, 'children_count was '.var_export($event->children_count, true));

        return 'select `smoke_events`.*';
    });

    $check('get() honours columns and returns stdClass', function () use ($connection, $table, $expect) {
        $row = $connection->table($table)->where('id', 'evt_1')->get(['id', 'name'])->first();

        $expect($row instanceof stdClass, 'row was '.get_debug_type($row));
        $expect(! property_exists($row, 'visits'), 'get($columns) was ignored');

        return null;
    });

    $check('cursor() streams rows', function () use ($connection, $table, $expect) {
        $ids = [];

        foreach ($connection->cursor("select id from `{$table}` order by id") as $row) {
            $ids[] = $row->id;
        }

        $expect($ids === ['evt_1', 'evt_2', 'evt_3'], 'got '.implode(',', $ids));

        return null;
    });

    $check('aggregates and pagination', function () use ($expect) {
        $count = SmokeEvent::count();
        $page = SmokeEvent::orderBy('id')->paginate(perPage: 2, page: 2);

        $expect($count === 3, "count was {$count}");
        $expect($page->total() === 3, 'total was '.$page->total());
        $expect($page->count() === 1, 'page 2 had '.$page->count().' rows');

        return null;
    });

    echo "\nValue hydration\n";

    $check('TIMESTAMP hydrates through $casts', function () use ($expect) {
        $event = SmokeEvent::find('evt_1');

        $expect($event->occurred_at instanceof Carbon, 'got '.get_debug_type($event->occurred_at));
        $expect(
            $event->occurred_at->format('Y-m-d H:i:s.u') === '2026-03-04 05:06:07.891011',
            'got '.$event->occurred_at->format('Y-m-d H:i:s.u')
        );

        return 'microseconds preserved';
    });

    $check('NUMERIC, DATE and BYTES unwrap to PHP values', function () use ($expect) {
        $event = SmokeEvent::find('evt_1');

        $expect($event->amount === '1.5', 'amount was '.var_export($event->amount, true));
        $expect($event->occurred_on === '2026-01-02', 'date was '.var_export($event->occurred_on, true));
        $expect($event->raw === 'abc', 'bytes was '.get_debug_type($event->raw));

        return 'BigQuery normalises 1.50 to 1.5';
    });

    $check('NUMERIC keeps precision a float would lose', function () use ($connection, $expect) {
        $row = $connection->select("select numeric '12345678901234567890.123456789' as big")[0];

        $expect($row->big === '12345678901234567890.123456789', 'got '.var_export($row->big, true));

        return null;
    });

    $check('model survives toArray() and json encoding', function () use ($expect) {
        $json = SmokeEvent::find('evt_1')->toJson();

        $expect(is_string($json), 'toJson() failed');
        $expect(json_decode($json, true) !== null, 'invalid json: '.substr($json, 0, 80));

        return null;
    });

    echo "\nBigQuery-specific SQL\n";

    $check('whereDate casts the parameter', function () use ($expect) {
        $count = SmokeEvent::whereDate('occurred_at', '2026-03-04')->count();

        $expect($count === 1, "expected 1, got {$count}");

        return 'date(col) = cast(? as date)';
    });

    $check('whereMonth / whereYear cast to int64', function () use ($expect) {
        $byMonth = SmokeEvent::whereMonth('occurred_at', 3)->count();
        $byYear = SmokeEvent::whereYear('occurred_at', 2026)->count();

        $expect($byMonth === 1, "whereMonth expected 1, got {$byMonth}");
        $expect($byYear === 3, "whereYear expected 3, got {$byYear}");

        return 'extract(part from col) = cast(? as int64)';
    });

    $check('json_value() reads a JSON column', function () use ($expect) {
        $count = SmokeEvent::where('payload->theme', 'dark')->count();

        $expect($count === 2, "expected 2, got {$count}");

        return null;
    });

    $check('offset without limit', function () use ($expect) {
        $rows = SmokeEvent::orderBy('id')->offset(1)->get();

        $expect($rows->count() === 2, 'got '.$rows->count().' rows');

        return 'limit '.PHP_INT_MAX.' offset 1';
    });

    $check('inRandomOrder uses rand()', function () use ($expect) {
        $rows = SmokeEvent::inRandomOrder()->limit(2)->get();

        $expect($rows->count() === 2, 'got '.$rows->count().' rows');

        return null;
    });

    $check('whereIn and whereNull', function () use ($expect) {
        $in = SmokeEvent::whereIn('id', ['evt_1', 'evt_2'])->count();
        $null = SmokeEvent::whereNull('name')->count();

        $expect($in === 2, "whereIn expected 2, got {$in}");
        $expect($null === 1, "whereNull expected 1, got {$null}");

        return null;
    });

    echo "\nBinding types\n";

    $check('DateTime binding against a TIMESTAMP column', function () use ($expect) {
        $count = SmokeEvent::where('occurred_at', '<', new DateTimeImmutable('2026-06-01'))->count();

        $expect($count === 2, "expected 2, got {$count}");

        return null;
    });

    $check('DATE column needs an explicit Date value (known limitation)', function () use ($connection, $expect) {
        try {
            SmokeEvent::where('occurred_on', '=', new DateTimeImmutable('2026-01-02'))->count();
        } catch (Throwable $exception) {
            $expect(
                str_contains($exception->getMessage(), 'DATE, TIMESTAMP'),
                'rejected for an unexpected reason: '.substr($exception->getMessage(), 0, 120)
            );

            $count = SmokeEvent::where(
                'occurred_on', '=', $connection->getClient()->date(new DateTimeImmutable('2026-01-02'))
            )->count();

            $expect($count === 1, "Date value object expected 1, got {$count}");

            return 'plain DateTime rejected, ->date() works';
        }

        $expect(false, 'a plain DateTime was accepted for a DATE column');
    });

    $check('NUMERIC column needs an explicit Numeric value (known limitation)', function () use ($connection, $expect) {
        try {
            SmokeEvent::where('amount', '=', '1.50')->count();
        } catch (Throwable $exception) {
            $expect(
                str_contains($exception->getMessage(), 'NUMERIC, STRING'),
                'rejected for an unexpected reason: '.substr($exception->getMessage(), 0, 120)
            );

            $count = SmokeEvent::where('amount', '=', $connection->getClient()->numeric('1.50'))->count();

            $expect($count === 1, "Numeric value object expected 1, got {$count}");

            return 'plain string rejected, ->numeric() works';
        }

        $expect(false, 'a plain string was accepted for a NUMERIC column');
    });

    echo "\nWrites\n";

    if (! $dmlAvailable) {
        foreach ([
            'insert with a null column',
            'update to null',
            'update with a raw expression',
            'model update qualifies updated_at',
            'delete($id) scopes to that key',
            'unfiltered update gets where true',
            'unfiltered delete gets where true',
        ] as $name) {
            $skip($name, $dmlReason);
        }
    }

    if ($dmlAvailable) {
        $check('insert with a null column', function () use ($connection, $table, $expect) {
            $ok = $connection->table($table)->insert([
                'id' => 'evt_4', 'name' => 'later', 'visits' => 1, 'parent_id' => null,
            ]);

            $expect($ok === true, 'insert() did not return true');

            return 'values (?, ?, ?, null)';
        });

        $check('update to null', function () use ($connection, $table, $expect) {
            $affected = $connection->table($table)->where('id', 'evt_4')->update(['name' => null]);

            $expect($affected === 1, "expected 1 affected, got {$affected}");

            return 'set `name` = null';
        });

        $check('update with a raw expression', function () use ($connection, $table, $expect) {
            $affected = $connection->table($table)
                ->where('id', 'evt_4')
                ->update(['visits' => $connection->raw('visits + 1')]);

            $expect($affected === 1, "expected 1 affected, got {$affected}");

            return null;
        });

        $check('model update qualifies updated_at', function () use ($expect) {
            $affected = SmokeEvent::where('id', 'evt_1')->update(['name' => 'signup-v2']);

            $expect($affected === 1, "expected 1 affected, got {$affected}");
            $expect(SmokeEvent::find('evt_1')->name === 'signup-v2', 'value did not change');

            return 'set `name` = ?, `smoke_events`.`updated_at` = ?';
        });

        $check('delete($id) scopes to that key', function () use ($connection, $table, $expect) {
            $affected = $connection->table($table)->delete('evt_4');

            $expect($affected === 1, "expected 1 affected, got {$affected}");

            return 'where `smoke_events`.`id` = ?';
        });

        $check('unfiltered update gets where true', function () use ($connection, $table, $expect) {
            $affected = $connection->table($table)->update(['ratio' => 9.5]);

            $expect($affected === 3, "expected 3 affected, got {$affected}");

            return null;
        });

        $check('unfiltered delete gets where true', function () use ($connection, $table, $expect) {
            $affected = $connection->table($table)->delete();

            $expect($affected === 3, "expected 3 affected, got {$affected}");

            return null;
        });
    }

    echo "\nSafety\n";

    $check('pretend() does not execute', function () use ($connection, $table, $expect) {
        $before = $connection->table($table)->count();

        $logged = $connection->pretend(function ($pretending) use ($table) {
            $pretending->table($table)->delete();
        });

        $expect($logged !== [], 'pretend() logged nothing');
        $expect($connection->table($table)->count() === $before, 'pretend() actually deleted rows');

        return null;
    });

    $check('maximum_bytes_billed is enforced', function () use ($project, $location, $expect) {
        $guarded = new BigQueryConnection([
            'driver' => 'bigquery', 'project_id' => $project,
            'dataset' => 'samples', 'location' => $location,
            'maximum_bytes_billed' => 1024,
        ]);

        try {
            $guarded->select('select word from `bigquery-public-data`.`samples`.`shakespeare` limit 1');
        } catch (Throwable $exception) {
            return 'rejected as expected';
        }

        $expect(false, 'query was not rejected by the byte ceiling');
    });

    $check('row locking is refused', function () use ($expect) {
        try {
            SmokeEvent::lockForUpdate()->get();
        } catch (LogicException $exception) {
            return 'LogicException';
        }

        $expect(false, 'lockForUpdate() did not throw');
    });
} finally {
    if (! getenv('BIGQUERY_SMOKE_KEEP')) {
        $client->dataset($dataset)->delete(['deleteContents' => true]);
        echo "\nDropped dataset {$dataset}\n";
    }
}

printf("\n%d passed, %d skipped, %d failed\n", $passed, $skipped, count($failed));

foreach ($failed as [$name, $message]) {
    printf("  - %s: %s\n", $name, substr($message, 0, 200));
}

exit($failed === [] ? 0 : 1);
