<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Enabled Health Checks
    |--------------------------------------------------------------------------
    | List of enabled checks. Comment out or remove to disable.
    */
    'checks' => [
        'database' => true,
        'redis' => true,
        'cache' => true,
        'storage' => true,
        'queue' => true,
        'mail' => true,
        'disk-space' => true,
        'migrations' => true,
        'env-config' => true,
        'loki' => true,
        'logging' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Loki Endpoint
    |--------------------------------------------------------------------------
    */
    'loki_url' => env('LOKI_URL', null),
];
