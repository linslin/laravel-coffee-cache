<?php

namespace linslin\CoffeeCache\Facades;

use linslin\CoffeeCache\Services\CoffeeCache as CoffeeCacheService;

/**
 * Class Facade
 * @package linslin\CoffeeCache
 *
 * @method static void someMethod(string $message)
 *
 * @see CoffeeCache
 */
class CoffeeCache extends \Illuminate\Support\Facades\Facade
{

    /**
     * {@inheritDoc}
     */
    protected static function getFacadeAccessor()
    {
        return CoffeeCacheService::class;
    }
}
