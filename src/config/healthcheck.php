<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Core System Checks
    |--------------------------------------------------------------------------
    | These checks validate essential Laravel components.
    */
    'core' => [
        'database' => env('HEALTHCHECK_DATABASE', true),
        'cache' => env('HEALTHCHECK_CACHE', true),
        'queue' => env('HEALTHCHECK_QUEUE', true),
        'mail' => env('HEALTHCHECK_MAIL', false),
        'migrations' => env('HEALTHCHECK_MIGRATIONS', true),
        'env-config' => env('HEALTHCHECK_ENV_CONFIG', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Infrastructure Checks
    |--------------------------------------------------------------------------
    | These checks validate external services and system resources.
    */
    'infrastructure' => [
        'redis' => env('HEALTHCHECK_REDIS', false),
        'storage' => env('HEALTHCHECK_STORAGE', true),
        'disk-space' => env('HEALTHCHECK_DISK_SPACE', true),
        'logging' => env('HEALTHCHECK_LOGGING', true),
        'loki' => env('HEALTHCHECK_LOKI', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | External Service Configuration
    |--------------------------------------------------------------------------
    | URLs and credentials for external integrations.
    */
    'services' => [
        'loki_url' => env('LOKI_URL', null),
    ],

];