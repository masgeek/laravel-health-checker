<?php

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Masgeek\HealthCheck\Services\HealthCheckService;

it('checks the configured Redis service', function () {
    $redis = Mockery::mock();
    $redis->shouldReceive('info')->once()->andReturn([
        'redis_version' => '7.2.4',
        'executable' => 'redis-server',
        'Memory' => [
            'used_memory_human' => '1.00M',
            'used_memory_peak_human' => '2.00M',
        ],
    ]);
    Redis::shouldReceive('connection')->once()->andReturn($redis);

    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['redis' => true],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['checks']['redis']['status'])->toBe('UP');
});

it('checks the configured mail transport', function () {
    Mail::shouldReceive('getSymfonyTransport')->once()->andReturn('smtp');

    config([
        'healthcheck.core' => ['mail' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['checks']['mail'])->toMatchArray([
        'status' => 'UP',
        'transport' => 'smtp',
    ]);
});

it('returns a failed Loki status for an unsuccessful response', function () {
    Http::fake([
        '*' => Http::response([], 503),
    ]);

    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['loki' => true],
        'healthcheck.services.loki_url' => 'http://loki.test:3100',
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('unhealthy');
    expect($result['checks']['loki']['status'])->toBe('DOWN');
    expect($result['checks']['loki']['error'])->toBe('Loki unreachable');
});
