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
     * @param string $routePath
     * @return bool
     * @throws \Exception
     */
    public function clearCacheFile (string $routePath)
    {
        // try delete cache file
        $desktopResult = $this->getDriver()->clearCacheFile(sha1($this->getHost().$routePath).'-desktop');
        $mobileResult = $this->getDriver()->clearCacheFile(sha1($this->getHost().$routePath).'-mobile');

        return $desktopResult || $mobileResult;
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

    /**
     * @param string $routePath
     * @param string $type mobile|desktop
     * @return bool
     * @throws \Exception
     */
    public function cacheFileExists (string $routePath, string $type)
    {
        return $this->getDriver()->cacheFileExists(sha1($this->getHost().$routePath).'-'.$type);
    }


    /**
     * @param string $routePath
     * @param string $type mobile|desktop
     * @return bool|Carbon
     * @throws \Exception
     */
    public function getCacheFileCreatedDate (string $routePath, string $type) {

        return $this->getDriver()->getCacheFileCreatedDate(sha1($this->getHost().$routePath).'-'.$type);
    }
}
