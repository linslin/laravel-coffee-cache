<?php

require_once './../vendor/linslin/laravel-coffee-cache/CoffeeCache.php';

$coffeeCache = new CoffeeCache(__DIR__);
$coffeeCache->cacheTime = 60 * 60 * 24 * 1; //Default is one day. 60 * 60 * 24 * 1 = 1 day
$coffeeCache->enabledHosts = [
    'www.production.com',
    'subdomain.production.com',
]; // optional, leave this array empty if you want to cache all domains
$coffeeCache->handle();


/**
 * Laravel - A PHP Framework For Web Artisans
 *
 * @package  Laravel
 * @author   Taylor Otwell <taylor@laravel.com>
 */

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| our application. We just need to utilize it! We'll simply require it
| into the script here so that we don't have to worry about manual
| loading any of our classes later on. It feels great to relax.
|
*/

require __DIR__.'/../vendor/autoload.php';

/*
|--------------------------------------------------------------------------
| Turn On The Lights
|--------------------------------------------------------------------------
|
| We need to illuminate PHP development, so let us turn on the lights.
| This bootstraps the framework and gets it ready for use, then it
| will load up this application so that we can run it and send
| the responses back to the browser and delight our users.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request
| through the kernel, and send the associated response back to
| the client's browser allowing them to enjoy the creative
| and wonderful application we have prepared for them.
|
*/

$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

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
