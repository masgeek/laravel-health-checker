<?php

use Illuminate\Support\Facades\Http;
use Masgeek\HealthCheck\Services\HealthCheckService;

it('runs only enabled checks and returns the documented result contract', function () {
    config([
        'healthcheck.core' => ['cache' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result)->toHaveKeys(['status', 'timestamp', 'checks']);
    expect($result['status'])->toBe('healthy');
    expect($result['checks'])->toHaveKey('cache');
    expect($result['checks'])->not->toHaveKey('database');
    expect($result['timestamp'])->toMatch('/^\d{4}-\d{2}-\d{2}T/');
});

it('reports the configured SQLite database check as up', function () {
    config([
        'healthcheck.core' => ['database' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('healthy');
    expect($result['checks']['database']['status'])->toBe('UP');
    expect($result['checks']['database']['database_type'])->toBe('sqlite');
});

it('checks PHP extensions with a top-level status', function () {
    config([
        'healthcheck.php_extensions' => ['json'],
        'healthcheck.core' => ['php-extensions' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('healthy');
    expect($result['checks']['php-extensions'])->toMatchArray([
        'status' => 'UP',
        'extensions' => ['json' => true],
    ]);
});

it('checks the configured Loki service through the HTTP client', function () {
    Http::fake([
        '*' => Http::response([
            'buildDate' => '2026-01-01',
            'version' => '3.0.0',
            'go_version' => 'go1.24',
        ]),
    ]);

    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['loki' => true],
        'healthcheck.services.loki_url' => 'http://loki.test:3100',
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('healthy');
    expect($result['checks']['loki']['status'])->toBe('UP');
});
