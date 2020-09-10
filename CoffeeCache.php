<?php

/**
 * Class coffeeCache
 *
 * @property int $cacheTime
 * @property string $cacheDirPath
 * @property string $cachedFilename
 */
class CoffeeCache {


    // ############################################### Class variables // ##############################################


    /**
     * Cache time in seconds
     *
     * seconds * seconds per minutes * hours per day * days
     * 60 * 60 * 24 * 1 = 1 day
     * 60 * 60 * 24 * 10 = 10 days
     *
     * @var int
     */
    public $cacheTime = 60 * 60 * 24 * 1;

    /**
     * @var float
     */
    private $diskSpaceAllowedToUse = 95.00;

    /**
     * No caching if false
     * @var bool
     */
    public $cacheEnabled = true;

    /**
     * Minify cache file
     */
    public $minifyCacheFile = false;

    /**
     * @var string
     */
    private $cacheDirPath = '';

    /**
     * Cached filename
     * @var string
     */
    private $cachedFilename = '';

    /**
     * Enabled hosts list. Optional, leave it as empty array if you want to cache all domains.
     */
    public $enabledHosts = [];

    /**
     * List of enabled http status codes, default is 200 OK.
     * @var string[]
     */
    public $enabledHttpStatusCodes = [
        '200'
    ];

    /**
     * @var null|int
     */
    public $httpStatusCode = null;

    /**
     * @var string[]
     */
    public $excludeUrls = [];


    // ################################################ Class methods // ###############################################


    /**
     * CoffeeCache constructor.
     *
     * @param string $publicDir
     */
    public function __construct($publicDir)
    {
        //Init
        $this->cachedFilename = sha1($_SERVER['REQUEST_URI']);
        $this->cacheDirPath = $publicDir.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR
            .'storage'.DIRECTORY_SEPARATOR
            .'coffeeCache'.DIRECTORY_SEPARATOR;
    }


    /**
     * @return bool
     */
    public function isCacheEnabled ()
    {
        return !isset($_COOKIE['disable-cache']) && $this->cacheEnabled;
    }


    /**
     * @return bool
     */
    public function isCacheAble ()
    {
        //init
        $domainShouldBeCached = false;

        if (sizeof($this->enabledHosts) > 0) {
            
            $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['SERVER_NAME'];

            foreach ($this->enabledHosts as $cachedHostName) {
                if (strpos($host, $cachedHostName) !== false) {
                    $domainShouldBeCached = true;
                    break;
                }
            }
        } else {
            $domainShouldBeCached = true;
        }

        return $_SERVER['REQUEST_METHOD'] === 'GET'
            && $domainShouldBeCached
            && $this->isCacheEnabled()
            && $this->spaceLeftOnDevice()
            && !$this->detectExcludedUrl();
    }


    /**
     * Handle request for caching
     */
    public function handle ()
    {
        if ($this->isCacheAble()) {

            $directoryName = substr($this->cachedFilename, 0 ,4);

            if (file_exists($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename)
                && filemtime($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename) + $this->cacheTime > time()) {
                header('coffee-cache: 1');
                echo file_get_contents($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename);
                exit;
            } else {
                ob_start();
            }
        }
    }


    /**
     * Finalize cache. Write file to disk is caching is enabled
     */
    public function finalize ()
    {
        if ($this->isCacheAble() && $this->detectStatusCode()) {

            $directoryName = substr($this->cachedFilename, 0 ,4);

            if (!is_dir($this->cacheDirPath.DIRECTORY_SEPARATOR.$directoryName)) {
                mkdir($this->cacheDirPath.DIRECTORY_SEPARATOR.$directoryName);
            }

            try {
                file_put_contents(
                    $this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename,
                    $this->minifyCacheFile(ob_get_contents())
                );
            } catch (Exception $exception) {
                //log this later
                if (file_exists($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename)) {
                    unlink($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename);
                }
            }

            ob_end_clean();
            $this->handle();
        }
    }


    /**
     * @param string $cacheFileData
     * @return string
     */
    private function minifyCacheFile (string $cacheFileData)
    {
        if ($this->minifyCacheFile) {
            $cacheFileData = preg_replace([
                '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
                '/[^\S ]+\</s',     // strip whitespaces before tags, except space
                '/(\s)+/s',         // shorten multiple whitespace sequences
                '/<!--(.|\s)*?-->/' // Remove HTML comments
            ], [
                '>',
                '<',
                '\\1',
                ''
            ],
                $cacheFileData
            );
        }

        return $cacheFileData;
    }


    /**
     * Check if there is space left on the device for caching
     * @return bool
     */
    private function spaceLeftOnDevice ()
    {
        return (disk_free_space("/") / disk_total_space("/"))  * 100 <= $this->diskSpaceAllowedToUse;
    }

    /**
     * @return bool
     */
    private function detectStatusCode ()
    {
        return in_array((string)$this->httpStatusCode, $this->enabledHttpStatusCodes);
    }


    /**
     * @return bool
     */
    private function detectExcludedUrl ()
    {
        if (sizeof($this->excludeUrls) > 0) {
            foreach ($this->excludeUrls as $excludeUrl) {
                if (strpos($_SERVER['REQUEST_URI'], $excludeUrl) !== false) {
                    return true;
                }
            }
        }

        return false;
    }
}
