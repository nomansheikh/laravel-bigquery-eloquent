<?php

namespace NomanSheikh\LaravelBigqueryEloquent;

use NomanSheikh\LaravelBigqueryEloquent\Database\BigQueryConnection;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaravelBigqueryEloquentServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-bigquery-eloquent')
            ->hasConfigFile('bigquery-eloquent');
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        $db = $this->app['db'];

        $db->extend('bigquery', function (array $config, string $name) {
            // Merge config/bigquery.php defaults
            $config = array_merge([
                'project_id' => config('bigquery.project_id'),
                'key_file' => config('bigquery.key_file'),
                'dataset' => config('bigquery.dataset'),
            ], $config);

            return new BigQueryConnection($config);
        });
    }
}
