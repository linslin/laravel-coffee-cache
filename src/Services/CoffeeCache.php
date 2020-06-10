<?php

namespace linslin\CoffeeCache\Services;

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

}
