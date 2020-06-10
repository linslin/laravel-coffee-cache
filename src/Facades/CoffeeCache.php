<?php

namespace linslin\CoffeeCache\Facades;

use Carbon\Carbon;
use linslin\CoffeeCache\Services\CoffeeCache as CoffeeCacheService;

/**
 * Class Facade
 * @package linslin\CoffeeCache
 *
 * @method static void clearCacheFile(string $routePath)
 * @method static boolean cacheFileExists(string $routePath)
 * @method static boolean|Carbon getCacheFileCreatedDate(string $routePath)
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
