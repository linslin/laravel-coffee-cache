<?php

namespace linslin\CoffeeCache\Driver;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;


/**
 * Class RedisDriver
 * @package linslin\CoffeeCache\Driver
 */
class RedisDriver
{

    /**
     *
     */
    /**
     * @var null|\Redis
     */
    private $redisConnection = null;


    /**
     * @return Redis|\Redis|null
     */
    private function getConnection () {
        if (!$this->redisConnection) {

            $this->redisConnection = new \Redis();
            $this->redisConnection->connect(
                config('coffeeCache.redis.host'),
                config('coffeeCache.redis.port'),
                config('coffeeCache.redis.timeout')
            );

            if (strlen(config('coffeeCache.redis.password')) !== 0) {
                $this->redisConnection->auth(config('coffeeCache.redis.password'));
            }
        }

        return $this->redisConnection;
    }


    /**
     * @param string $routePath // e.g. test/page without domain.
     * @return array
     */
    public function clearCacheFile (string $cacheKey)
    {
        if ($this->cacheFileExists($cacheKey)) {
            return $this->getConnection()->del($cacheKey);
        }
    }


    /**
     * Deletes all cache files in cache dir
     */
    public function clearCache ()
    {
        return $this->getConnection()->flushAll();
    }


    /**
     * @param string $routePath
     * @return bool
     */
    public function cacheFileExists (string $cacheKey)
    {
        return $this->getConnection()->exists($cacheKey);
    }


    /**
     * Not supported by redis.
     *
     * @param string $routePath
     * @return bool|Carbon
     * @throws \Exception
     */
    public function getCacheFileCreatedDate (string $cacheKey) {
        return false;
    }
}
