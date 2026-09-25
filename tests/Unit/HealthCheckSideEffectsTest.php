<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Masgeek\HealthCheck\Services\HealthCheckService;

it('cleans up the cache probe after a successful check', function () {
    Cache::shouldReceive('put')->once()->andReturn(true);
    Cache::shouldReceive('get')->once()->andReturn('test');
    Cache::shouldReceive('forget')->once()->andReturn(true);
    Cache::shouldReceive('getDefaultDriver')->once()->andReturn('array');

    config([
        'healthcheck.core' => ['cache' => true],
        'healthcheck.infrastructure' => [],
    ]);

    expect((new HealthCheckService())->run()['checks']['cache']['status'])->toBe('UP');
});

it('cleans up storage even when the probe fails', function () {
    Storage::shouldReceive('put')->once()->andThrow(new RuntimeException('write failed'));
    Storage::shouldReceive('delete')->once()->andReturn(true);

    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['storage' => true],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('unhealthy');
    expect($result['checks']['storage']['status'])->toBe('DOWN');
});
