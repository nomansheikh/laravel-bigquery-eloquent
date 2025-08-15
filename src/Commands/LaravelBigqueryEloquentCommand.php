<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Commands;

use Illuminate\Console\Command;

class LaravelBigqueryEloquentCommand extends Command
{
    public $signature = 'laravel-bigquery-eloquent';

    public $description = 'My command';

    public function handle(): int
    {
        $this->comment('All done');

        return self::SUCCESS;
    }
}
