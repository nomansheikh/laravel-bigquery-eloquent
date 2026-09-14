# Laravel BigQuery Eloquent

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nomansheikh/laravel-bigquery-eloquent.svg?style=flat-square)](https://packagist.org/packages/nomansheikh/laravel-bigquery-eloquent)  
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nomansheikh/laravel-bigquery-eloquent/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nomansheikh/laravel-bigquery-eloquent/actions?query=workflow%3Arun-tests+branch%3Amain)  
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nomansheikh/laravel-bigquery-eloquent/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nomansheikh/laravel-bigquery-eloquent/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)  
[![Total Downloads](https://img.shields.io/packagist/dt/nomansheikh/laravel-bigquery-eloquent.svg?style=flat-square)](https://packagist.org/packages/nomansheikh/laravel-bigquery-eloquent)

---

## Overview

**Laravel BigQuery Eloquent** is a Laravel package that seamlessly integrates Google BigQuery with Laravel's Eloquent ORM. It enables you to query BigQuery tables using familiar Eloquent syntax, simplifying analytics and data querying directly within your Laravel applications.

---

## Features

- **Eloquent Integration**: Use BigQuery tables as Eloquent models, including relations, `find()`, aggregates, and pagination.
- **Dedicated BigQuery Driver**: Optimized database driver for BigQuery.
- **Automatic Fully Qualified Table Names**: Handles `project.dataset.table` formatting transparently.
- **Custom Query Grammar**: Generates GoogleSQL — `EXTRACT` for date parts, `RAND()`, `JSON_VALUE()`, and correctly backtick-quoted identifiers.
- **Full DML Support**: `select`, `insert`, `update`, and `delete` via Eloquent or the raw query builder, executed as BigQuery DML.
- **Raw Statements**: `DB::statement()`, `DB::select()`, `DB::update()`, `DB::cursor()`, and `DB::pretend()` all work against BigQuery.
- **Bindings That Just Work**: `Carbon` / `DateTimeInterface` values are auto-wrapped as BigQuery `Timestamp`, microseconds are preserved, and `null` bindings are handled.
- **Native Values Unwrapped**: `TIMESTAMP`, `DATE`, `NUMERIC`, `BYTES`, and nested `STRUCT`/`ARRAY` values arrive as plain PHP values that Eloquent casts understand.
- **Cost Controls**: Per-connection `maximum_bytes_billed`, `job_timeout_ms`, and job `labels`.
- **Flexible Authentication**: Supports Application Default Credentials (ADC) and service account key files.
- **Environment Configuration**: Easy setup via environment variables.

---

## Requirements

- PHP 8.3 or higher
- Laravel 11.x or 12.x
- Access to Google Cloud BigQuery API
- Google Cloud authentication (Application Default Credentials recommended)

---

## Installation

Install the package via Composer:

```bash
composer require nomansheikh/laravel-bigquery-eloquent
```

---

## Configuration

### 1. Publish the configuration file

Run the following Artisan command to publish the package config:

```bash
php artisan vendor:publish --provider="NomanSheikh\LaravelBigqueryEloquent\LaravelBigqueryEloquentServiceProvider"
```

### 2. Authentication Setup

The package supports Google Cloud's recommended authentication hierarchy:

- **Recommended: Application Default Credentials (ADC)**
  - Local development: Run `gcloud auth application-default login`
  - Production: Use a service account attached to your compute instance or set the `GOOGLE_APPLICATION_CREDENTIALS` environment variable.

- **Alternative: Service Account Key File**
  - Download a JSON key file from Google Cloud Console.
  - Set the `BIGQUERY_KEY_FILE` environment variable pointing to the JSON file (not recommended for production).

#### Authentication Hierarchy

The Google Client library authenticates in this order:

1. `key_file` specified in the database config.
2. `GOOGLE_APPLICATION_CREDENTIALS` environment variable.
3. Default credential file locations.
4. Google App Engine built-in service account.
5. Google Compute Engine built-in service account.
6. Direct credentials array in config.

Example direct credentials array in `config/database.php`:

```php
'bigquery' => [
    'driver'     => 'bigquery',
    'project_id' => env('BIGQUERY_PROJECT_ID', ''),
    'dataset'    => env('BIGQUERY_DATASET', ''),
    'key_file'   => [
        'type' => env('GOOGLE_CLOUD_ACCOUNT_TYPE'),
        'private_key_id' => env('GOOGLE_CLOUD_PRIVATE_KEY_ID'),
        'private_key' => env('GOOGLE_CLOUD_PRIVATE_KEY'),
        'client_email' => env('GOOGLE_CLOUD_CLIENT_EMAIL'),
        'client_id' => env('GOOGLE_CLOUD_CLIENT_ID'),
        'auth_uri' => env('GOOGLE_CLOUD_AUTH_URI'),
        'token_uri' => env('GOOGLE_CLOUD_TOKEN_URI'),
        'auth_provider_x509_cert_url' => env('GOOGLE_CLOUD_AUTH_PROVIDER_CERT_URL'),
        'client_x509_cert_url' => env('GOOGLE_CLOUD_CLIENT_CERT_URL'),
    ],
],
```

### 3. Environment Variables

Add the following to your `.env` file:

```env
BIGQUERY_PROJECT_ID=your-project-id
BIGQUERY_DATASET=your-dataset-name

# Required for datasets outside the US multi-region, e.g. "EU" or "asia-northeast1"
# BIGQUERY_LOCATION=EU

# Optional cost and runtime guards
# BIGQUERY_MAXIMUM_BYTES_BILLED=10737418240
# BIGQUERY_JOB_TIMEOUT_MS=60000

# Optional: Only if using service account key file (not recommended for production)
# BIGQUERY_KEY_FILE=path/to/your/service-account-key.json
```

### 4. Database Connection

Add the BigQuery connection in `config/database.php`:

```php
'connections' => [
    // ... other connections ...

    'bigquery' => [
        'driver'     => 'bigquery',
        'project_id' => env('BIGQUERY_PROJECT_ID', ''),
        'dataset'    => env('BIGQUERY_DATASET', ''),

        // The region the dataset lives in. Queries against a dataset outside the
        // US multi-region fail unless this is set.
        'location'   => env('BIGQUERY_LOCATION'),

        // Cancels a query before it is billed if the planner estimates it will
        // scan more than this many bytes.
        'maximum_bytes_billed' => env('BIGQUERY_MAXIMUM_BYTES_BILLED'),

        'job_timeout_ms' => env('BIGQUERY_JOB_TIMEOUT_MS'),

        // Attached to every job, useful for attributing BigQuery spend.
        'labels' => ['service' => 'my-app'],

        // Optional: Only if using service account key file (not recommended)
        'key_file'   => env('BIGQUERY_KEY_FILE', ''),
    ],
],
```

Every key also has a package-level default in `config/bigquery-eloquent.php`, which is used
when the connection itself does not define it.

---

## Usage

### Models

Extend `BigQueryModel` to interact with BigQuery tables. Because BigQuery has no auto-incrementing
primary keys, `BigQueryModel` defaults to `$incrementing = false` and `$keyType = 'string'`, so
models assign their own keys (typically a ULID or UUID):

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

class UserAnalytics extends BigQueryModel
{
    use HasUlids;

    protected $table = 'user_analytics'; // Automatically prefixed with project.dataset

    protected $fillable = ['user_id', 'page_views', 'session_duration'];
}
```

### Reading

```php
// Basic query
$users = UserAnalytics::where('page_views', '>', 100)->get();

// Complex query with ordering and limits
$topUsers = UserAnalytics::select('user_id', 'page_views')
    ->where('created_at', '>=', now()->subDays(30))
    ->orderBy('page_views', 'desc')
    ->limit(10)
    ->get();

// Aggregations
$stats = UserAnalytics::selectRaw('
    COUNT(*) as total_users,
    AVG(page_views) as avg_page_views,
    SUM(session_duration) as total_duration
')->first();
```

### Writing

`insert`, `update`, and `delete` are executed as BigQuery DML statements. Be aware of [BigQuery's DML quotas](https://cloud.google.com/bigquery/quotas#dml) — DML is intended for batch and analytical workloads, not high-frequency OLTP writes.

```php
// Insert via Eloquent
$row = UserAnalytics::create([
    'user_id'          => 'usr_42',
    'page_views'       => 17,
    'session_duration' => 312,
]);

// Update
UserAnalytics::where('user_id', 'usr_42')->update(['page_views' => 18]);

// Delete
UserAnalytics::where('user_id', 'usr_42')->delete();

// Batch insert via the query builder
DB::connection('bigquery')->table('project.dataset.user_analytics')->insert([
    ['user_id' => 'usr_1', 'page_views' => 5],
    ['user_id' => 'usr_2', 'page_views' => 9],
]);
```

`Carbon` / `DateTimeInterface` values in bindings are automatically wrapped as BigQuery `Timestamp`, and the package preserves microsecond precision when serializing dates.

### Raw Queries

Execute raw SQL directly via the BigQuery connection:

```php
use Illuminate\Support\Facades\DB;

$results = DB::connection('bigquery')->select(
    'SELECT user_id, COUNT(*) as visits FROM `project.dataset.user_analytics` WHERE created_at >= ?',
    [now()->subDays(7)]
);

// DDL and any other statement BigQuery accepts
DB::connection('bigquery')->statement(
    'CREATE TABLE IF NOT EXISTS `project.dataset.user_analytics` (user_id STRING, page_views INT64)'
);

// Stream a large result set without buffering it
foreach (DB::connection('bigquery')->cursor('SELECT * FROM `project.dataset.events`') as $row) {
    // ...
}

// See the SQL without running (and paying for) the query
$queries = DB::connection('bigquery')->pretend(
    fn () => UserAnalytics::where('page_views', '>', 100)->get()
);
```

---

## Limitations

These are inherent BigQuery characteristics, not bugs in the package:

- **No transactions.** `DB::transaction()`, `beginTransaction()`, `commit()`, and `rollBack()` throw `LogicException`. BigQuery supports session-scoped transactions but they are not wired up here.
- **No auto-incrementing primary keys.** Assign your own key (ULID/UUID). `insertGetId()` throws `LogicException` to make this explicit.
- **DML, not streaming.** Inserts, updates, and deletes execute as DML statements and are subject to [BigQuery's DML quotas](https://cloud.google.com/bigquery/quotas#dml). For high-volume ingestion, use a batch load job or the streaming insert API directly via the underlying `BigQueryClient` (`DB::connection('bigquery')->getClient()`).
- **No row locking.** `lockForUpdate()` and `sharedLock()` throw `LogicException`.
- **No joins in `UPDATE` / `DELETE`.** BigQuery has no such syntax; both throw `LogicException`. Use a `MERGE` statement via `DB::connection('bigquery')->statement()`, or a subquery in the `WHERE` clause.
- **No `upsert()`.** Write a `MERGE` statement instead.
- **No schema builder.** `Schema::hasTable()`, `Schema::create()`, and friends throw `LogicException`. Run DDL with `DB::connection('bigquery')->statement()`.
- **No PDO.** `getPdo()` / `getReadPdo()` throw `LogicException`. Code or third-party packages that introspect the underlying PDO will not work.
- **BigQuery-specific driver.** Not interchangeable with other Laravel database drivers.

---

## Upgrading from v2 to v3

v3 fixes identifier quoting and column qualification, which changes the SQL the package emits.

- **Columns are now backtick-quoted per segment.** `t.user_id` compiles to `` `t`.`user_id` `` instead of being passed through raw, and table aliases are quoted too. Identifiers containing backticks now have them stripped rather than being interpolated verbatim, which closes an injection hole in `orderBy()` / `where()` when the column name came from request input.
- **Columns are qualified with the table reference, not the full path.** `Model::find()`, relations, and `withCount()` previously emitted `project.dataset.table.column`, which BigQuery rejects. They now emit `table.column`.
- **`get()` returns `stdClass` rows**, matching Laravel's query builder contract, instead of arrays. Eloquent models are unaffected.
- **BigQuery value objects are unwrapped.** `TIMESTAMP`, `DATE`, `NUMERIC`, and `BYTES` columns hydrate as PHP strings rather than `Google\Cloud\BigQuery\*` objects, so `$casts` works as expected.
- **`BigQueryModel` defaults to `$incrementing = false` and `$keyType = 'string'`.** Models that already set these are unaffected.
- **`delete($id)` now scopes to that key** instead of ignoring the argument and deleting everything matching the current constraints.
- **The empty `LaravelBigqueryEloquent` class, its facade, and the `LaravelBigqueryEloquent` alias were removed.** None of them were ever functional.

---

## Testing

Run the test suite with:

```bash
composer test
```

---

## Contributing

Contributions are welcome. Please open an issue or a pull request.

---

## Security

If you discover any security vulnerabilities, please report them via [our security policy](../../security/policy).

---

## Credits

- [Noman Sheikh](https://github.com/nomansheikh)
- [All Contributors](../../contributors)

---

## License

This package is open-source software licensed under the [MIT License](LICENSE.md).
