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

        return $_SERVER['REQUEST_METHOD'] === 'GET' && $domainShouldBeCached;
    }


    /**
     * Handle request for caching
     */
    public function handle ()
    {
        if ($this->isCacheAble()) {

            if (file_exists($this->cacheDirPath.$this->cachedFilename)
                && filemtime($this->cacheDirPath.$this->cachedFilename) + $this->cacheTime > time()) {
                echo file_get_contents($this->cacheDirPath.$this->cachedFilename);
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
            file_put_contents($this->cacheDirPath.$this->cachedFilename,  ob_get_contents());
            ob_end_clean();
            $this->handle();
        }
    }

    /**
     * @return bool
     */
    private function detectStatusCode ()
    {
        return in_array((string)$this->httpStatusCode, $this->enabledHttpStatusCodes);
    }
}
