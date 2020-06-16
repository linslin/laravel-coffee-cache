<?php

namespace linslin\CoffeeCache\Services;

use Carbon\Carbon;

/**
 * Class ViewHelpers
 * @package App\Services
 */
class CoffeeCache
{

    /**
     * @param string $routePath // e.g. test/page without domain.
     * @return array
     */
    public function clearCacheFile (string $routePath)
    {
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.sha1($routePath);

        if (file_exists($cacheFilePath) && !is_dir($cacheFilePath)) {
            unlink($cacheFilePath);
        }
    }


    /**
     * Deletes all cache files in cache dir
     */
    public function clearCache ()
    {
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR;

        if (is_dir($cacheFilePath)) {
            $files = glob($cacheFilePath.'*');
            foreach($files as $file){
                if(is_file($file) && !strpos($file, '.gitignore')) {
                    unlink($file);
                }
            }
        }
    }


    /**
     * @param string $routePath
     * @return bool
     */
    public function cacheFileExists (string $routePath)
    {
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.sha1($routePath);

        return file_exists($cacheFilePath) && !is_dir($cacheFilePath);
    }


    /**
     * @param string $routePath
     * @return bool|Carbon
     * @throws \Exception
     */
    public function getCacheFileCreatedDate (string $routePath) {

        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.sha1($routePath);

        if ($this->cacheFileExists($routePath)) {
            return new Carbon(filemtime($cacheFilePath));
        }

        return false;
    }
}
