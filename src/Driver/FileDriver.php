<?php

namespace linslin\CoffeeCache\Driver;

use Carbon\Carbon;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\File;


/**
 * Class FileDriver
 * @package linslin\CoffeeCache\Driver
 */
class FileDriver
{

    /**
     * Deletes cache files older than the configured cache lifetime and removes
     * empty hash directories.
     *
     * @param int $cacheTime Cache lifetime in seconds
     * @return array
     */
    public function pruneExpiredCache ($cacheTime)
    {
        if (!is_int($cacheTime) || $cacheTime < 1) {
            throw new \InvalidArgumentException('Cache time must be a positive integer.');
        }

        $cachePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR;
        $result = [
            'deleted_files' => 0,
            'deleted_directories' => 0,
            'reclaimed_bytes' => 0,
            'failed_files' => 0,
        ];

        if (!is_dir($cachePath)) {
            return $result;
        }

        $expiresBefore = time() - $cacheTime;
        $directories = glob($cachePath.'*', GLOB_ONLYDIR);

        foreach ($directories ?: [] as $directoryPath) {
            $files = glob($directoryPath.DIRECTORY_SEPARATOR.'*');

            foreach ($files ?: [] as $filePath) {
                if (!is_file($filePath)
                    || !preg_match('/^[a-f0-9]{40}-(desktop|mobile)$/', basename($filePath))) {
                    continue;
                }

                $modifiedAt = filemtime($filePath);
                if ($modifiedAt === false || $modifiedAt > $expiresBefore) {
                    continue;
                }

                $size = filesize($filePath);
                if (@unlink($filePath)) {
                    $result['deleted_files']++;
                    $result['reclaimed_bytes'] += $size === false ? 0 : $size;
                } else {
                    $result['failed_files']++;
                }
            }

            if ($this->isEmptyDir($directoryPath) && @rmdir($directoryPath)) {
                $result['deleted_directories']++;
            }
        }

        return $result;
    }

    /**
     * @param string $routePath // e.g. test/page without domain.
     * @return array
     */
    public function clearCacheFile (string $routePath)
    {
        $directoryPath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.substr($routePath, 0, 4).DIRECTORY_SEPARATOR;
        $cacheFilePath = $directoryPath.$routePath;

        if (file_exists($cacheFilePath) && !is_dir($cacheFilePath)) {
            unlink($cacheFilePath);

            if ($this->isEmptyDir($directoryPath)) {
                rmdir($directoryPath);
            }
        }
    }


    /**
     * Deletes all cache files in cache dir
     */
    public function clearCache ()
    {
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR;

        foreach (File::directories($cacheFilePath) as $parentFolderName) {

            $subCacheFilePath = $parentFolderName.DIRECTORY_SEPARATOR;

            if (is_dir($subCacheFilePath)) {
                $files = glob($subCacheFilePath.'*');
                foreach($files as $file){
                    if(is_file($file) && !strpos($file, '.gitignore')) {
                        unlink($file);
                    }
                }
            }

            //remove dir
            rmdir($subCacheFilePath);
        }

    }


    /**
     * @param string $routePath
     * @return bool
     */
    public function cacheFileExists (string $routePath)
    {
        $directory = substr($routePath, 0, 4);
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.$routePath;

        return file_exists($cacheFilePath) && !is_dir($cacheFilePath);
    }


    /**
     * @param string $routePath
     * @return bool|Carbon
     * @throws \Exception
     */
    public function getCacheFileCreatedDate (string $routePath) {

        $directory = substr($routePath, 0, 4);
        $cacheFilePath = storage_path().DIRECTORY_SEPARATOR.'coffeeCache'.DIRECTORY_SEPARATOR.$directory.DIRECTORY_SEPARATOR.$routePath;

        if ($this->cacheFileExists($routePath)) {
            return new Carbon(filemtime($cacheFilePath));
        }

        return false;
    }


    /**
     * @param string $directoryPath
     * @return bool
     */
    private function isEmptyDir (string $directoryPath)
    {
        $FileSystem = new Filesystem();
        return empty($FileSystem->files($directoryPath));
    }
}
