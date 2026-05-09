<?php

namespace NomanSheikh\LaravelBigqueryEloquent;

use Illuminate\Database\Eloquent\Model;
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

    public function packageRegistered(): void
    {
        $this->app->resolving('db', function ($db) {
            $db->extend('bigquery', function (array $config, string $name) {
                $config = array_merge([
                    'project_id' => config('bigquery-eloquent.project_id'),
                    'key_file' => config('bigquery-eloquent.key_file'),
                    'dataset' => config('bigquery-eloquent.dataset'),
                    'name' => $name,
                ], $config);

                return new BigQueryConnection($config);
            });
        });
    }

    public function packageBooted(): void
    {
        parent::packageBooted();

        Model::setConnectionResolver($this->app['db']);
    }
}
