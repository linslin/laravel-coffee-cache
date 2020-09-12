<?php

namespace linslin\CoffeeCache\Services;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;
use linslin\CoffeeCache\Driver\FileDriver;
use linslin\CoffeeCache\Driver\RedisDriver;

/**
 * Class CoffeeCache
 * @package linslin\CoffeeCache\Services
 */
class CoffeeCache
{

    /**
     * @return mixed
     */
    private function getHost ()
    {
        //Init
        $host = '';

        if (isset($_SERVER['HTTP_X_FORWARDED_HOST'])) {
            $host = $_SERVER['HTTP_X_FORWARDED_HOST'];
        } else if (isset($_SERVER['SERVER_NAME'])) {
            $host = $_SERVER['SERVER_NAME'];
        }

        return $host;
    }

    /**
     * @return FileDriver|RedisDriver
     * @throws \Exception
     */
    private function getDriver ()
    {
        if (!config()->has('coffeeCache')) {
            throw new \Exception('coffeeCache config not found in ./config/coffeeCache.php');
        }

        switch (config('coffeeCache.driver')) {

            case 'file':
                return new FileDriver();
                break;

            case 'redis':
                return new RedisDriver();
                break;

            default:
                throw new \Exception('Unknown driver "'.config('coffeeCache.driver').'" for coffeeCache. Allowed are "redis" or "file".');
                break;
        }

    }

    /**
     * @param string $routePath // e.g. test/page without domain.
     * @return array
     */
    public function clearCacheFile (string $routePath)
    {
        return $this->getDriver()->clearCacheFile(sha1($this->getHost().$routePath));
    }


    /**
     * Deletes all cache files in cache dir
     */
    public function clearCache ()
    {
        return $this->getDriver()->clearCache();
    }


    /**
     * @param string $routePath
     * @return bool
     */
    public function cacheFileExists (string $routePath)
    {
        return $this->getDriver()->cacheFileExists(sha1($this->getHost().$routePath));
    }


    /**
     * @param string $routePath
     * @return bool|Carbon
     * @throws \Exception
     */
    public function getCacheFileCreatedDate (string $routePath) {

        return $this->getDriver()->getCacheFileCreatedDate(sha1($this->getHost().$routePath));
    }
}
