<?php

namespace NomanSheikh\LaravelBigqueryEloquent;

use Illuminate\Database\DatabaseManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelBigqueryEloquentServiceProvider extends PackageServiceProvider
{
    /**
     * @var array<int, string>
     */
    protected array $connectionConfigKeys = [
        'project_id',
        'key_file',
        'dataset',
        'location',
        'maximum_bytes_billed',
        'job_timeout_ms',
        'labels',
    ];

    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-bigquery-eloquent')
            ->hasConfigFile('bigquery-eloquent');
    }

    public function packageRegistered(): void
    {
        $this->app->resolving('db', function (DatabaseManager $db) {
            $db->extend('bigquery', function (array $config, string $name) {
                return new BigQueryConnection($this->mergePackageDefaults($config, $name));
            });
        });
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    protected function mergePackageDefaults(array $config, string $name): array
    {
        $defaults = ['name' => $name];

        foreach ($this->connectionConfigKeys as $key) {
            $defaults[$key] = config("bigquery-eloquent.{$key}");
        }

        return array_merge($defaults, array_filter(
            $config,
            fn (mixed $value): bool => $value !== null
        ));
    }
}
