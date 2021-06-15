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
 * @property string $host
 * @property boolean $cacheEnabled
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
            $this->getCachedContent();;
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
                    header('coffee-cache-f: 1');
                    echo file_get_contents($this->cacheDirPath . $directoryName . DIRECTORY_SEPARATOR . $this->cachedFilename);
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
                        header('coffee-cache-r: 1');
                        echo $redisClient->get($this->cachedFilename);
                        exit;
                    } else {
                        ob_start();
                    }

                } catch (Exception $e) {
                }
                break;
        }
    }

    /**
     * Set cached content
     */
    private function setCachedContent()
    {
        $cacheData = $this->minifyCacheFile(ob_get_contents());

        if ($this->isCacheAble() && strlen($cacheData) > 0 && $this->detectStatusCode()) {

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
        return (disk_free_space("/") / disk_total_space("/")) * 100 <= $this->diskSpaceAllowedToUse;
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
        $useragent = $_SERVER['HTTP_USER_AGENT'];

        if(preg_match('/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows (ce|phone)|xda|xiino/i',$useragent)||preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\-(n|u)|c55\/|capi|ccwa|cdm\-|cell|chtm|cldc|cmd\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\-s|devi|dica|dmob|do(c|p)o|ds(12|\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\-|_)|g1 u|g560|gene|gf\-5|g\-mo|go(\.w|od)|gr(ad|un)|haie|hcit|hd\-(m|p|t)|hei\-|hi(pt|ta)|hp( i|ip)|hs\-c|ht(c(\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\-(20|go|ma)|i230|iac( |\-|\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\/)|klon|kpt |kwc\-|kyo(c|k)|le(no|xi)|lg( g|\/(k|l|u)|50|54|\-[a-w])|libw|lynx|m1\-w|m3ga|m50\/|ma(te|ui|xo)|mc(01|21|ca)|m\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\-2|po(ck|rt|se)|prox|psio|pt\-g|qa\-a|qc(07|12|21|32|60|\-[2-7]|i\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\-|oo|p\-)|sdk\/|se(c(\-|0|1)|47|mc|nd|ri)|sgh\-|shar|sie(\-|m)|sk\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\-|v\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\-|tdg\-|tel(i|m)|tim\-|t\-mo|to(pl|sh)|ts(70|m\-|m3|m5)|tx\-9|up(\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\-|your|zeto|zte\-/i',substr($useragent,0,4))) {
            return 'mobile';
        }

        return 'desktop';
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
