<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled Health Checks
    |--------------------------------------------------------------------------
    | Each check can be toggled via environment variables.
    | Example: HEALTHCHECK_DATABASE=false
    */
    'checks' => [
        'database'     => env('HEALTHCHECK_DATABASE', true),
        'redis'        => env('HEALTHCHECK_REDIS', false),
        'cache'        => env('HEALTHCHECK_CACHE', true),
        'storage'      => env('HEALTHCHECK_STORAGE', true),
        'queue'        => env('HEALTHCHECK_QUEUE', true),
        'mail'         => env('HEALTHCHECK_MAIL', true),
        'disk-space'   => env('HEALTHCHECK_DISK_SPACE', true),
        'migrations'   => env('HEALTHCHECK_MIGRATIONS', false),
        'env-config'   => env('HEALTHCHECK_ENV_CONFIG', true),
        'loki'         => env('HEALTHCHECK_LOKI', false),
        'logging'      => env('HEALTHCHECK_LOGGING', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Loki Endpoint
    |--------------------------------------------------------------------------
    | Set via LOKI_URL in your .env file
    */
    'loki_url' => env('LOKI_URL', null),

];