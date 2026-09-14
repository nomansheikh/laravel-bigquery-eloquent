<?php

use Google\Cloud\BigQuery\Timestamp;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;
use NomanSheikh\LaravelBigqueryEloquent\Tests\Support\BigQuerySpy;

class AnalyticsEvent extends BigQueryModel
{
    protected $table = 'events';

    protected $guarded = [];

    protected $casts = ['occurred_at' => 'datetime'];

    public $timestamps = false;

    public function children(): HasMany
    {
        return $this->hasMany(AnalyticsEvent::class, 'parent_id', 'id');
    }
}

it('finds a record by key with SQL BigQuery accepts', function () {
    $spy = BigQuerySpy::attach(DB::connection('bigquery'), [['id' => 'evt_1', 'name' => 'signup']]);

    $event = AnalyticsEvent::find('evt_1');

    expect($spy->sql)->toBe('select * from `test-project`.`default_dataset`.`events` where `events`.`id` = ? limit 1')
        ->and($spy->parameters)->toBe(['evt_1'])
        ->and($event)->toBeInstanceOf(AnalyticsEvent::class)
        ->and($event->name)->toBe('signup');
});

it('casts an unwrapped BigQuery timestamp through Eloquent', function () {
    BigQuerySpy::attach(DB::connection('bigquery'), [[
        'id' => 'evt_1',
        'occurred_at' => new Timestamp(new DateTimeImmutable('2026-03-04 05:06:07.891011', new DateTimeZone('UTC'))),
    ]]);

    $event = AnalyticsEvent::find('evt_1');

    expect($event->occurred_at)->toBeInstanceOf(Carbon::class)
        ->and($event->occurred_at->format('Y-m-d H:i:s.u'))->toBe('2026-03-04 05:06:07.891011');
});

it('creates a record without asking BigQuery for an inserted id', function () {
    $spy = BigQuerySpy::attach(DB::connection('bigquery'));

    $event = AnalyticsEvent::create(['id' => 'evt_2', 'name' => 'purchase']);

    expect($spy->sql)->toBe('insert into `test-project`.`default_dataset`.`events` (`id`, `name`) values (?, ?)')
        ->and($spy->parameters)->toBe(['evt_2', 'purchase'])
        ->and($event->exists)->toBeTrue();
});

it('updates a record through the model', function () {
    $spy = BigQuerySpy::attach(DB::connection('bigquery'), info: ['numDmlAffectedRows' => '1']);

    $event = AnalyticsEvent::make(['id' => 'evt_2', 'name' => 'purchase']);
    $event->exists = true;
    $event->syncOriginal();
    $event->name = 'refund';
    $event->save();

    expect($spy->sql)->toBe('update `test-project`.`default_dataset`.`events` set `name` = ? where `id` = ?')
        ->and($spy->parameters)->toBe(['refund', 'evt_2']);
});

it('deletes a record through the model', function () {
    $spy = BigQuerySpy::attach(DB::connection('bigquery'), info: ['numDmlAffectedRows' => '1']);

    $event = AnalyticsEvent::make(['id' => 'evt_2']);
    $event->exists = true;
    $event->syncOriginal();
    $event->delete();

    expect($spy->sql)->toBe('delete from `test-project`.`default_dataset`.`events` where `id` = ?');
});

it('counts records', function () {
    $spy = BigQuerySpy::attach(DB::connection('bigquery'), [['aggregate' => 12]]);

    expect(AnalyticsEvent::where('name', 'signup')->count())->toBe(12)
        ->and($spy->sql)->toBe('select count(*) as aggregate from `test-project`.`default_dataset`.`events` where `name` = ?');
});

it('paginates', function () {
    BigQuerySpy::attach(DB::connection('bigquery'), [['aggregate' => 1, 'id' => 'evt_1']]);

    $page = AnalyticsEvent::paginate(perPage: 5, page: 2);

    expect($page->perPage())->toBe(5)
        ->and($page->currentPage())->toBe(2);
});

it('qualifies the updated at column with the table reference', function () {
    $spy = BigQuerySpy::attach(DB::connection('bigquery'), info: ['numDmlAffectedRows' => '1']);

    TimestampedEvent::where('id', 'evt_3')->update(['name' => 'refund']);

    expect($spy->sql)->toBe('update `test-project`.`default_dataset`.`events` set `name` = ?, `events`.`updated_at` = ? where `id` = ?');
});

it('selects the table reference rather than the full path for withCount', function () {
    $spy = BigQuerySpy::attach(DB::connection('bigquery'));

    AnalyticsEvent::withCount('children')->get();

    expect($spy->sql)->toStartWith('select `events`.*, (select count(*) from `test-project`.`default_dataset`.`events`');
});

class TimestampedEvent extends BigQueryModel
{
    protected $table = 'events';

    protected $guarded = [];
}
