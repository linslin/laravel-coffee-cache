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
     * @var string
     */
    private $cacheDirPath = '';

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
     * CoffeeCache constructor.
     */
    private $cachedFilename = '';


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
        return $_SERVER['REQUEST_METHOD'] === 'GET';
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
        if ($this->isCacheAble()) {
            file_put_contents($this->cacheDirPath.$this->cachedFilename,  ob_get_contents());
            ob_end_clean();
            $this->handle();
        }
    }

}
