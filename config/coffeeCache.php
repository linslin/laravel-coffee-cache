<?php

return [

    /**
     * Cache driver: 'file' or 'redis'
     */
    'driver' => 'file',

    /*
     * Redis connection
     */
    'redis' => [
        'host' => 'localhost',
        'port' => 6000,
        'password' => '', //leave empty if no password is given
        'timeout' => 0.5
    ],
];
