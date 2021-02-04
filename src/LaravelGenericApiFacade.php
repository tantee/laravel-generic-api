<?php

namespace TaNteE\LaravelGenericApi;

use Illuminate\Support\Facades\Facade;

/**
 * @see \TaNteE\LaravelGenericApi\LaravelGenericApi
 */
class LaravelGenericApiFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'laravel-generic-api';
    }
}
