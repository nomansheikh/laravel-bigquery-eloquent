<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use NomanSheikh\LaravelBigqueryEloquent\LaravelBigqueryEloquentServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'NomanSheikh\\LaravelBigqueryEloquent\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            LaravelBigqueryEloquentServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        /*
         foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__ . '/database/migrations') as $migration) {
            (include $migration->getRealPath())->up();
         }
         */
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'bigquery');

        $app['config']->set('database.connections.bigquery', [
            'driver' => 'bigquery',
            'project_id' => 'test-project',
            'dataset' => 'default_dataset',
        ]);
    }
}
