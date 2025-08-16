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

- **Eloquent Integration**: Use BigQuery tables as Eloquent models.
- **Dedicated BigQuery Driver**: Optimized database driver for BigQuery.
- **Automatic Fully Qualified Table Names**: Handles `project.dataset.table` formatting transparently.
- **Custom Query Grammar**: Generates SQL optimized for BigQuery syntax.
- **Read-Only Support**: Supports SELECT queries (read operations only).
- **Flexible Authentication**: Supports Application Default Credentials (ADC) and service account key files.
- **Environment Configuration**: Easy setup via environment variables.

---

## Requirements

- PHP 8.3 or higher
- Laravel 10.x, 11.x, or 12.x
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
        // Optional: Only if using service account key file (not recommended)
        'key_file'   => env('BIGQUERY_KEY_FILE', ''),
    ],
],
```

---

## Usage

### Models

Create models by extending `BigQueryModel` to interact with BigQuery tables:

```php
<?php

namespace App\Models;

use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

class UserAnalytics extends BigQueryModel
{
    protected $table = 'user_analytics'; // Automatically prefixed with project.dataset
}
```

#### Overriding Dataset

You can override the default dataset either by setting the `$dataset` property or dynamically:

```php
<?php

namespace App\Models;

use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

class SalesData extends BigQueryModel
{
    protected $table = 'sales';

    // Override dataset statically
    protected ?string $dataset = 'analytics';

    // Or override dynamically in constructor
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setDataset('sales_data');
    }
}
```

### Queries

Perform queries using familiar Eloquent methods:

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

### JSON Support

Query JSON fields using BigQuery's JSON functions:

```php
// Using whereRaw with JSON_EXTRACT_SCALAR
$results = UserAnalytics::whereRaw("JSON_EXTRACT_SCALAR(json_column, '$.key') = ?", ['value'])->get();

// Using selectRaw for JSON extraction
$results = UserAnalytics::selectRaw("JSON_EXTRACT_SCALAR(json_column, '$.key') as key_value")->get();
```

### Raw Queries

Execute raw SQL queries directly via the BigQuery connection:

```php
use Illuminate\Support\Facades\DB;

$results = DB::connection('bigquery')->select(
    'SELECT user_id, COUNT(*) as visits FROM `project.dataset.user_analytics` WHERE created_at >= ?',
    [now()->subDays(7)]
);
```

---

## Limitations

- **Read-Only**: Supports only read operations (SELECT queries). Write operations (INSERT, UPDATE, DELETE) are not supported.
- **BigQuery Specific**: Designed specifically for Google BigQuery and may not be compatible with other database drivers.

---

## Testing

Run the test suite with:

```bash
composer test
```

---

## Contributing

Contributions are welcome! Please see [CONTRIBUTING](CONTRIBUTING.md) for guidelines.

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
