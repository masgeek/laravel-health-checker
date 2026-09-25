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
        'failed-jobs' => env('HEALTHCHECK_FAILED_JOBS', false),
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
        'outbound' => env('HEALTHCHECK_OUTBOUND', false),
        'certificate' => env('HEALTHCHECK_CERTIFICATE', false),
        'config-cache' => env('HEALTHCHECK_CONFIG_CACHE', false),
        'build' => env('HEALTHCHECK_BUILD', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | External Service Configuration
    |--------------------------------------------------------------------------
    | URLs and credentials for external integrations.
    */
    'route' => [
        'path' => env('HEALTHCHECK_PATH', 'health'),
        'middleware' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('HEALTHCHECK_MIDDLEWARE', 'throttle:60'))
        ))),
    ],

    'expose_details' => env('HEALTHCHECK_EXPOSE_DETAILS', false),

    'php_extensions' => [
        'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath',
    ],

    'disk_space_path' => env('HEALTHCHECK_DISK_SPACE_PATH', null),
    'failed_jobs_recent_minutes' => 60,

    'services' => [
        'loki_url' => env('LOKI_URL', null),
        'outbound_urls' => [],
        'outbound_timeout' => 3,
        'certificate_host' => env('HEALTHCHECK_CERTIFICATE_HOST', null),
        'certificate_port' => 443,
        'certificate_warning_days' => 14,
        'build_version' => env('HEALTHCHECK_BUILD_VERSION', null),
        'build_commit' => env('HEALTHCHECK_BUILD_COMMIT', null),
    ],

];
