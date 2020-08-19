<?php

namespace linslin\CoffeeCache\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\File;

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
        $directory = substr($routePath, 0, 4);
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.sha1($routePath);

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

        foreach (File::directories($cacheFilePath) as $parentFolderName) {

            $subCacheFilePath = $cacheFilePath.$parentFolderName.DIRECTORY_SEPARATOR;

            if (is_dir($subCacheFilePath.)) {
                $files = glob($subCacheFilePath.'*');
                foreach($files as $file){
                    if(is_file($file) && !strpos($file, '.gitignore')) {
                        unlink($file);
                    }
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
        $directory = substr($routePath, 0, 4);
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.sha1($routePath);

        return file_exists($cacheFilePath) && !is_dir($cacheFilePath);
    }


    /**
     * @param string $routePath
     * @return bool|Carbon
     * @throws \Exception
     */
    public function getCacheFileCreatedDate (string $routePath) {

        $directory = substr($routePath, 0, 4);
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.sha1($routePath);

        if ($this->cacheFileExists($routePath)) {
            return new Carbon(filemtime($cacheFilePath));
        }

        return false;
    }
}
