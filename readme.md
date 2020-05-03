# ☕ laravel-coffee-cache
File based lever out view cache for **laravel 4.x, 5.x 6.x, 7.x** . This cache hook in before composer autoload and Laravel boostrapping. 
It will push your application into light speed. By default, all GET-Requests will be cached.

It's a coffee cache. You can drink more coffee instead of spending time to optimize your application or server 
environment. Mokka Mokka!

## Why and when should you use laravel-coffee-cache?
Laravel getting bigger and bigger over the years. Today laravel is a very nice framework which helps you to speed up your
software development and programming in its best way. On the other hand laravel is slow in handling requests and consumes
a lot of memory per request, even if you use View or Database caches. The bootstrapping of laravel takes a bit time
also it consumes a lot of memory. E.g. if you want to render an "imprint / disclaimer" page which doesn't have any 
dynamic data in its view. So, why you want to bootstrap laravel and all its dependencies just for returning a simple
HTML page?

☕ **laravel-coffee-cache** allows you to lever out laravel and composer autoload completely once a cache file has been
generated for a specific route (Request URI). In this way it consumes so much less hardware resources (CPU, RAM, Hard Disk) 
for each request. It will be push your application into light speed. 

**The difference to existing cache systems for laravel is:** You don't need to have a DB cache based on memcached or 
even a view file based cache placed in a laravel middleware. **Hint:** It's nice approach is to combine your DB Cache with 
☕ **laravel-coffee-cache**. Use your DB Cache even if you have ☕ **laravel-coffee-cache** running in the foreground. 

You will be able to create highly frequented web applications and save a lot of hardware resources 
(which also saves money) with ☕ **laravel-coffee-cache**. It makes to optimize your applications if your website is too slow
or your server / server capacities (CPU, Memory) run at full load. Give it a try. 

## Installation
    composer require --prefer-dist linslin/laravel-coffee-cache "*"

## Configuration and usage

- Create a cache folder name `coffeeCache` in `app/storage/`. So you have this folder created: `app/storage/coffeeCache`.
- Add a `.gitignore` in `app/storage/coffeeCache` and put [this contents](https://github.com/linslin/laravel-coffee-cache/blob/master/app/storage/coffeeCache/.gitignore) into. 
- Edit your `app/public/index.php` and add this lines on the top of your PHP script:

    ```php
    require_once './../vendor/linslin/laravel-coffee-cache/CoffeeCache.php';
    
    $coffeeCache = new CoffeeCache(__DIR__);
    $coffeeCache->cacheTime = 60 * 60 * 24 * 1; //Default is one day. 60 * 60 * 24 * 1 = 1 day
    $coffeeCache->handle();
    ```
    Replace all code lines under `$kernel = $app->make('Illuminate\Contracts\Http\Kernel');` with this lines:
    ```php
    /** @var Illuminate\Http\Response $response */
    $response = $kernel->handle(
        $request = Illuminate\Http\Request::capture()
    );
    
    if ($coffeeCache->isCacheAble()) {
        $response->sendHeaders();
        echo $response->content();
    } else {
        $response->send();
    }
    
    $kernel->terminate($request, $response);
    
    $coffeeCache->finalize();
    ```
    You can also compare your edits with this [example index.php](https://github.com/linslin/laravel-coffee-cache/blob/master/app/public/index.php). 
    
    **In the end your `app/public/index.php` should look like this:**
    ```php
    <?php
    require_once './../vendor/linslin/laravel-coffee-cache/CoffeeCache.php';
    
    $coffeeCache = new CoffeeCache(__DIR__);
    $coffeeCache->cacheTime = 60 * 60 * 24 * 1; //Default is one day. 60 * 60 * 24 * 1 = 1 day
    $coffeeCache->handle();
    
    
    require __DIR__.'/../bootstrap/autoload.php';
    $app = require_once __DIR__.'/../bootstrap/app.php';
    $kernel = $app->make('Illuminate\Contracts\Http\Kernel');
    
    /** @var Illuminate\Http\Response $response */
    $response = $kernel->handle(
        $request = Illuminate\Http\Request::capture()
    );
    
    if ($coffeeCache->isCacheAble()) {
        $response->sendHeaders();
        echo $response->content();
    } else {
        $response->send();
    }
    
    $kernel->terminate($request, $response);
    
    $coffeeCache->finalize();
    ```
- laravel-coffee-cache should now start to cache your GET Requests and creating cache files in `app/storage/coffeeCache`.
     

## Changelog

### 1.0.0 
- First stable release
