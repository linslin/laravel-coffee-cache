<?php

/**
 * Class coffeeCache
 *
 * @property int $cacheTime
 * @property string $cacheDirPath
 * @property string $cachedFilename
 * @property string $cacheDriver
 * @property string $httpStatusCode
 * @property string $contentType
 * @property boolean $cacheEnabled
 * @property boolean $minifyCacheFile
 * @property array $minifyIgnoreContentTypes
 * @property array $redisConnection
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
     * Ignore file endings from caching
     */
    public $minifyIgnoreContentTypes = [
        'image/png',
        'image/gif',
        'image/jpg',
        'image/jpeg',
    ];

    /**
     * Cache drive, "file" or "redis".
     * @var bool
     */
    public $cacheDriver = 'file';

    /**
     * Cache drive, "file" or "redis".
     * @var bool
     */
    public $redisConnection = [
        'host' => 'localhost',
        'port' => 5000,
        'password' => '',
        'timeout' => 0.5
    ];

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
     * @var null|int
     */
    public $contentType = null;

    /**
     * @var string[]
     */
    public $excludeUrls = [];

    /**
     * The current host
     */
    private $host = '';


    // ################################################ Class methods // ###############################################


    /**
     * CoffeeCache constructor.
     *
     * @param string $publicDir
     */
    public function __construct($publicDir)
    {

        //Init
        $this->host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['SERVER_NAME'];
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
            $this->getCachedContent();;
        }
    }


    /**
     * Finalize cache. Write file to disk is caching is enabled
     */
    public function finalize ()
    {
        $this->setCachedContent();
    }


    /**
     * Get cached content
     */
    private function getCachedContent ()
    {

        switch ($this->cacheDriver) {

            case 'file':

                $directoryName = substr($this->cachedFilename, 0 ,4);

                if (file_exists($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename)
                    && filemtime($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename) + $this->cacheTime > time()) {
                    header('coffee-cache-f: 1');
                    echo file_get_contents($this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename);
                    exit;
                } else {
                    ob_start();
                }
                break;

            case 'redis':


                try {

                    $redisClient = new Redis();
                    $redisClient->connect(
                        $this->redisConnection['host'],
                        $this->redisConnection['port'],
                        $this->redisConnection['timeout']
                    );
                    $redisClient->auth($this->redisConnection['password']);

                    if ($redisClient->exists($this->host.$this->cachedFilename)) {
                        header('coffee-cache-r: 1');
                        echo $redisClient->get($this->host.$this->cachedFilename);
                        exit;
                    } else {
                        ob_start();
                    }

                } catch (Exception $e) {}
                break;
        }
    }

    /**
     * Set cached content
     */
    private function setCachedContent ()
    {
        if ($this->isCacheAble() && $this->detectStatusCode()) {

            switch ($this->cacheDriver) {

                case 'file':

                    $directoryName = substr($this->cachedFilename, 0 ,4);

                    if (!is_dir($this->cacheDirPath.DIRECTORY_SEPARATOR.$directoryName)) {
                        mkdir($this->cacheDirPath.DIRECTORY_SEPARATOR.$directoryName);
                    }

                    try {

                        //write cache file
                        file_put_contents(
                            $this->cacheDirPath.$directoryName.DIRECTORY_SEPARATOR.$this->cachedFilename,
                            $this->minifyCacheFile(ob_get_contents())
                        );
                    } catch (Exception $exception) {
                        //log this later
                        if (file_exists($this->cacheDirPath.DIRECTORY_SEPARATOR.$directoryName)) {
                            unlink($this->cacheDirPath.DIRECTORY_SEPARATOR.$directoryName);
                        }
                    }

                    break;

                case 'redis':
                    try {
                        $redisClient = new Redis();
                        $redisClient->connect(
                            $this->redisConnection['host'],
                            $this->redisConnection['port'],
                            $this->redisConnection['timeout']
                        );
                        $redisClient->auth($this->redisConnection['password']);
                        $redisClient->setex(
                            $this->host.$this->cachedFilename,
                            $this->cacheTime,
                            $this->minifyCacheFile(ob_get_contents())
                        );

                    } catch (Exception $e) {
                    }

                    break;
            }


            ob_end_clean();
            $this->handle();
        }
    }


    /**
     * @return bool
     */
    private function minifyDetectContentTypeToIgnore ()
    {
        if ($this->contentType !== null) {
            return in_array(mb_strtolower($this->contentType), $this->minifyIgnoreContentTypes);
        }

        return false;
    }


    /**
     * @param string $cacheFileData
     * @return string
     */
    private function minifyCacheFile (string $cacheFileData)
    {

        if ($this->minifyCacheFile && !$this->minifyDetectContentTypeToIgnore()) {
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
