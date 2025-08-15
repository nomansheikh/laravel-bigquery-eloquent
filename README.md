# Laravel BigQuery Eloquent

[![Latest Version on Packagist](https://img.shields.io/packagist/v/nomansheikh/laravel-bigquery-eloquent.svg?style=flat-square)](https://packagist.org/packages/nomansheikh/laravel-bigquery-eloquent)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/workflow/status/nomansheikh/laravel-bigquery-eloquent/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/nomansheikh/laravel-bigquery-eloquent/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/actions/workflow/status/nomansheikh/laravel-bigquery-eloquent/fix-php-code-style-issues.yml?branch=main&label=code%20style&style=flat-square)](https://github.com/nomansheikh/laravel-bigquery-eloquent/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/nomansheikh/laravel-bigquery-eloquent.svg?style=flat-square)](https://packagist.org/packages/nomansheikh/laravel-bigquery-eloquent)

A Laravel package that provides seamless integration between Google BigQuery and Laravel's Eloquent ORM. Query BigQuery tables using familiar Eloquent syntax while leveraging BigQuery's powerful analytics capabilities.

## Features

- **Eloquent Integration**: Use BigQuery tables with Laravel's Eloquent ORM
- **Custom BigQuery Connection**: Dedicated database driver for BigQuery
- **Fully Qualified Table Names**: Automatic handling of `project.dataset.table` format
- **Query Grammar**: Custom SQL grammar optimized for BigQuery syntax
- **Read-Only Operations**: Currently supports read operations (SELECT queries)
- **Flexible Authentication**: Supports both Application Default Credentials (ADC) and service account key files
- **Environment Configuration**: Easy setup with environment variables

## Requirements

- PHP 8.3+
- Laravel 10.0+ / 11.0+ / 12.0+
- Google Cloud BigQuery API access
- Google Cloud authentication (Application Default Credentials recommended)

## Installation

You can install the package via composer:

```bash
composer require nomansheikh/laravel-bigquery-eloquent
```

## Configuration

### 1. Publish the configuration file

```bash
php artisan vendor:publish --tag="laravel-bigquery-eloquent-config"
```

### 2. Set up authentication

The package supports multiple authentication methods following Google Cloud's authentication hierarchy:

**Recommended: Application Default Credentials (ADC)**
- For local development: Run `gcloud auth application-default login`
- For production: Use service account attached to your compute instance or set `GOOGLE_APPLICATION_CREDENTIALS` environment variable

**Alternative: Service Account Key File**
- Download a service account key JSON file from Google Cloud Console
- Set the `BIGQUERY_KEY_FILE` environment variable (not recommended for production)

#### How Authentication Works

The Google Client library follows this authentication hierarchy (in order of preference):

1. **Key File Path in Config**: If `key_file` is specified in your database configuration
2. **`GOOGLE_APPLICATION_CREDENTIALS` Environment Variable**: Points to a service account JSON file
3. **Well-Known Path**: Automatically looks for credentials at:
   - **Windows**: `%APPDATA%/gcloud/application_default_credentials.json`
   - **Linux/macOS**: `$HOME/.config/gcloud/application_default_credentials.json`
4. **Google App Engine Built-in Service Account**: If running on Google App Engine
5. **Google Compute Engine Built-in Service Account**: If running on Google Compute Engine
6. **Direct Authentication Array**: You can also provide credentials directly in the config:

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

### 3. Set up your environment variables

Add these to your `.env` file:

```env
BIGQUERY_PROJECT_ID=your-project-id
BIGQUERY_DATASET=your-dataset-name
# Optional: Only if you need to use service account key file (not recommended)
# BIGQUERY_KEY_FILE=path/to/your/service-account-key.json
```

### 4. Configure database connection

Add the BigQuery connection to your `config/database.php`:

```php
'connections' => [
    // ... other connections
    
    'bigquery' => [
        'driver'     => 'bigquery',
        'project_id' => env('BIGQUERY_PROJECT_ID', ''),
        'dataset'    => env('BIGQUERY_DATASET', ''),
        // Optional: Only if you need to use service account key file (not recommended)
        'key_file'   => env('BIGQUERY_KEY_FILE', ''),
    ],
],
```

## Usage

### Creating BigQuery Models

Create models that extend `BigQueryModel` to work with BigQuery tables:

```php
<?php

namespace App\Models;

use NomanSheikh\LaravelBigqueryEloquent\Eloquent\BigQueryModel;

class UserAnalytics extends BigQueryModel
{
    protected $table = 'user_analytics'; // Will be prefixed with project.dataset
    
    // Optional: Override the dataset for this specific model
    protected ?string $dataset = 'analytics';
    
    // Define your fillable fields
    protected $fillable = [
        'user_id',
        'page_views',
        'session_duration',
        'created_at'
    ];
}
```

### Querying Data

Use standard Eloquent methods to query your BigQuery tables:

```php
// Basic queries
$users = UserAnalytics::where('page_views', '>', 100)->get();

// Complex queries
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

### Using Different Datasets

You can specify different datasets for specific models:

```php
class SalesData extends BigQueryModel
{
    protected $table = 'sales';
    
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);
        $this->setDataset('sales_data');
    }
}
```

### Raw Queries

You can also run raw SQL queries directly:

```php
use Illuminate\Support\Facades\DB;

$results = DB::connection('bigquery')
    ->select('SELECT user_id, COUNT(*) as visits FROM `project.dataset.user_analytics` WHERE created_at >= ?', [
        now()->subDays(7)
    ]);
```

## Current Limitations

- **Read-Only**: Currently supports read operations only. Write operations (INSERT, UPDATE, DELETE) are not yet implemented.
- **BigQuery Specific**: This package is specifically designed for BigQuery and may not work with other database systems.

## Testing

```bash
composer test
```

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Noman Sheikh](https://github.com/nomansheikh)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
