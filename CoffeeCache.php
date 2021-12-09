<?php

require_once __DIR__. DIRECTORY_SEPARATOR. '..' . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
    . 'mobiledetect' . DIRECTORY_SEPARATOR . 'mobiledetectlib' . DIRECTORY_SEPARATOR . 'Mobile_Detect.php';

/**
 * Class coffeeCache
 *
 * @property int $cacheTime
 * @property string $cacheDirPath
 * @property string $cachedFilename
 * @property string $cacheDriver
 * @property string $httpStatusCode
 * @property string $contentType
 * @property string $host
 * @property boolean $cacheEnabled
 * @property boolean $gzipEnabled
 * @property boolean $minifyCacheFile
 * @property boolean $cookieHandledCacheEnabled
 * @property array $minifyIgnoreContentTypes
 * @property array $redisConnection
 */
class CoffeeCache
{


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
     * GZIP cache files
     * @var bool
     */
    public $gzipEnabled = false;

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
     * Enabled hosts list which should be cached with session if a cookie cached=1 is set.
     */
    public $enabledCacheHostsWithSession = [];

    /**
     * Query parameters which should be excluded from the request uri for caching.
     */
    public $excludeQueryParam = [];

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
     * @var boolean
     */
    public $cookieHandledCacheEnabled = false;

    /**
     * @var array
     */
    public $excludeUrls = [];

    /**
     * Global string replacement markers
     * @var array
     */
    public $globalReplacements = [];

    /**
     * The current host
     */
    private $host = '';

    /**
     * The mobile detect service
     */
    private $mobileDetect = null;


    // ################################################ Class methods // ###############################################


    /**
     * CoffeeCache constructor.
     *
     * @param string $publicDir
     */
    public function __construct($publicDir)
    {
        //Init
        $this->mobileDetect = new Mobile_Detect;
        $this->host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['SERVER_NAME'];
        $this->cachedFilename = sha1($this->host.$this->getRequestUri()).'-'.$this->getAgent();
        $this->cacheDirPath = $publicDir . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR
            . 'storage' . DIRECTORY_SEPARATOR
            . 'coffeeCache' . DIRECTORY_SEPARATOR;
    }


    /**
     * @return bool
     */
    public function isCacheEnabled()
    {
        //init
        $cookieHandledCache = true;

        if ($this->cookieHandledCacheEnabled) {
            if (isset($_COOKIE['cached']) && $_COOKIE['cached'] === '1') {
                $cookieHandledCache = true;
            } else {
                $cookieHandledCache = false;
            }
        }

        return !isset($_COOKIE['disable-cache'])
            && $this->cacheEnabled
            && $cookieHandledCache;
    }


    /**
     * @return bool
     */
    public function isCacheAble()
    {
        //init
        $domainShouldBeCached = false;
        $domainShouldBeCachedWithSession = false;
        $host = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['SERVER_NAME'];

        if (sizeof($this->enabledHosts) > 0) {
            foreach ($this->enabledHosts as $cachedHostName) {
                if (strpos($host, $cachedHostName) !== false) {
                    $domainShouldBeCached = true;
                    break;
                }
            }
        } else {
            $domainShouldBeCached = true;
        }

        foreach ($this->enabledCacheHostsWithSession as $cachedHostNameWithSession) {
            if (strpos($this->host, $cachedHostNameWithSession) !== false) {
                $domainShouldBeCachedWithSession = true;
                break;
            }
        }

        $shouldBeCached = false;
        if ($domainShouldBeCachedWithSession) {
            $shouldBeCached = isset($_COOKIE['cached']) && $_COOKIE['cached'] === '1';
        } else {
            $shouldBeCached = $domainShouldBeCached;
        }

        return  $shouldBeCached
            && $_SERVER['REQUEST_METHOD'] === 'GET'
            && $this->isCacheEnabled()
            && $this->spaceLeftOnDevice()
            && !$this->detectExcludedUrl();
    }


    /**
     * Handle request for caching
     */
    public function handle()
    {
        if ($this->isCacheAble()) {
            $this->getCachedContent();
        }
    }


    /**
     * Finalize cache. Write file to disk is caching is enabled
     */
    public function finalize()
    {
        $this->setCachedContent();
    }


    /**
     * Get cached content
     */
    private function getCachedContent()
    {

        switch ($this->cacheDriver) {

            case 'file':

                $directoryName = substr($this->cachedFilename, 0, 4);

                if (file_exists($this->cacheDirPath . $directoryName . DIRECTORY_SEPARATOR . $this->cachedFilename)
                    && filemtime($this->cacheDirPath . $directoryName . DIRECTORY_SEPARATOR . $this->cachedFilename) + $this->cacheTime > time()) {

                    header('coffee-cache-'.$this->getAgent().'-file: 1');
                    $data = file_get_contents($this->cacheDirPath . $directoryName . DIRECTORY_SEPARATOR . $this->cachedFilename);

                    if ($this->gzipEnabled) {
                        $data = gzuncompress($data);
                    }

                    $data = $this->replaceGlobalMarkers($data);

                    echo $data;
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

                    if (strlen($this->redisConnection['password']) !== 0) {
                        $redisClient->auth($this->redisConnection['password']);
                    }

                    if ($redisClient->exists($this->cachedFilename)) {
                        header('coffee-cache-'.$this->getAgent().'-redis: 1');
                        $data = $redisClient->get($this->cachedFilename);

                        //update expire each time the key was hit
                        $redisClient->expire(
                            $this->cachedFilename,
                            $this->cacheTime
                        );

                        if ($this->gzipEnabled) {
                            $data = gzuncompress($data);
                        }

                        $data = $this->replaceGlobalMarkers($data);

                        //close redis connection
                        $redisClient->close();

                        echo $data;
                        exit;
                    } else {

                        //close redis connection, before ob start ;)
                        $redisClient->close();

                        ob_start();
                    }

                } catch (Exception $e) {
                }
                break;
        }
    }


    /**
     * @param string $data
     * @return string
     */
    private function replaceGlobalMarkers ($data)
    {
        if (sizeof($this->globalReplacements) > 0) {
            foreach ($this->globalReplacements as $globalReplacement) {
                switch ($globalReplacement['type']) {

                    case 'string':

                        if (isset($globalReplacement['markerEnd'])) {

                            $posStart = strpos($data, $globalReplacement['marker']);
                            $posEnd = strpos($data, $globalReplacement['markerEnd']);

                            if ($posStart !== false && $posEnd !== false) {
                                $firstPiece = mb_strcut($data, 0, $posStart + strlen($globalReplacement['marker']));
                                $secondPiece = mb_strcut(
                                    $data,
                                    $posEnd,
                                    strlen($data)
                                );

                                $data = $firstPiece.$globalReplacement['value'].$secondPiece;
                            }
                        } else {
                            $data = str_replace($globalReplacement['marker'], $globalReplacement['value'], $data);
                        }

                        break;

                    case 'file':

                        if (isset($globalReplacement['markerEnd'])) {
                            if (file_exists($globalReplacement['filePath'])) {

                                $posStart = strpos($data, $globalReplacement['marker']);
                                $posEnd = strpos($data, $globalReplacement['markerEnd']);

                                if ($posStart !== false && $posEnd !== false) {
                                    $firstPiece = mb_strcut($data, 0, $posStart + strlen($globalReplacement['marker']));
                                    $secondPiece = mb_strcut(
                                        $data,
                                        $posEnd,
                                        strlen($data)
                                    );

                                    $data = $firstPiece.file_get_contents($globalReplacement['filePath']).$secondPiece;
                                }
                            } else {
                                $data = str_replace($globalReplacement['marker'], '', $data);
                                $data = str_replace($globalReplacement['markerEnd'], '', $data);
                            }
                        } else {
                            if (file_exists($globalReplacement['filePath'])) {
                                $data = str_replace($globalReplacement['marker'], file_get_contents($globalReplacement['filePath']), $data);
                            } else {
                                $data = str_replace($globalReplacement['marker'], '', $data);
                            }
                        }
                        break;
                }
            }
        }

        return $data;
    }

    /**
     * Set cached content
     */
    private function setCachedContent()
    {
        $cacheData = $this->minifyCacheFile(ob_get_contents());

        if ($this->isCacheAble() && strlen($cacheData) > 0 && $this->detectStatusCode()) {

            if ($this->gzipEnabled) {
                $cacheData = gzcompress($cacheData);
            }

            switch ($this->cacheDriver) {

                case 'file':

                    $directoryName = substr($this->cachedFilename, 0, 4);

                    if (!is_dir($this->cacheDirPath . DIRECTORY_SEPARATOR . $directoryName)) {
                        mkdir($this->cacheDirPath . DIRECTORY_SEPARATOR . $directoryName);
                    }

                    try {

                        //write cache file
                        file_put_contents(
                            $this->cacheDirPath . $directoryName . DIRECTORY_SEPARATOR . $this->cachedFilename,
                            $cacheData
                        );
                    } catch (Exception $exception) {
                        //log this later
                        if (file_exists($this->cacheDirPath . DIRECTORY_SEPARATOR . $directoryName)) {
                            unlink($this->cacheDirPath . DIRECTORY_SEPARATOR . $directoryName);
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

                        if (strlen($this->redisConnection['password']) !== 0) {
                            $redisClient->auth($this->redisConnection['password']);
                        }

                        $redisClient->setex(
                            $this->cachedFilename,
                            $this->cacheTime,
                            $cacheData
                        );

                        //close redis connection
                        $redisClient->close();

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
    private function minifyDetectContentTypeToIgnore()
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
    private function minifyCacheFile(string $cacheFileData)
    {

        if ($this->minifyCacheFile && !$this->minifyDetectContentTypeToIgnore()) {
            $cacheFileData = str_replace(['    ', '   ', '   '], '', $cacheFileData);
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
    private function spaceLeftOnDevice()
    {
        if ($this->cacheDriver === 'file') {
            return (disk_free_space(__dir__) / disk_total_space(__dir__)) * 100 <= $this->diskSpaceAllowedToUse;
        }

        return true;
    }

    /**
     * @return bool
     */
    private function detectStatusCode()
    {
        return in_array((string)$this->httpStatusCode, $this->enabledHttpStatusCodes);
    }


    /**
     * @return bool
     */
    private function detectExcludedUrl()
    {
        if (sizeof($this->excludeUrls) > 0) {
            foreach ($this->excludeUrls as $excludeUrl) {
                if (strpos($this->getRequestUri(), $excludeUrl) !== false) {
                    return true;
                }
            }
        }

        return false;
    }


    /**
     * @return bool
     */
    private function getAgent()
    {
        return $this->mobileDetect->isMobile() ? 'mobile' : 'desktop';
    }


    /**
     * @return mixed|string
     */
    private function getRequestUri()
    {
        $requestUri = $_SERVER['REQUEST_URI'];
        $basePath = explode('?', $_SERVER['REQUEST_URI']);

        if (strpos($_SERVER['REQUEST_URI'], '?') && sizeof($_GET) > 0) {

            $params = $_GET;

            foreach ($this->excludeQueryParam as $keyParam) {
                if (isset($params[$keyParam])) {
                    unset($params[$keyParam]);
                }
            }

            if (sizeof($params) > 0) {
                $requestUri = $basePath[0].'?'.http_build_query($params);
            } else {
                $requestUri = $basePath[0];
            }
        }
        return $requestUri;
    }

}
