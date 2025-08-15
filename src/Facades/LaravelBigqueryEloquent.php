<?php

namespace NomanSheikh\LaravelBigqueryEloquent\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @see \NomanSheikh\LaravelBigqueryEloquent\LaravelBigqueryEloquent
 */
class LaravelBigqueryEloquent extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \NomanSheikh\LaravelBigqueryEloquent\LaravelBigqueryEloquent::class;
    }
}
