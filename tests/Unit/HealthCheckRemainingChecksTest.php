<?php

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Masgeek\HealthCheck\Services\HealthCheckService;

it('checks the configured queue connection', function () {
    $queue = Mockery::mock();
    $queue->shouldReceive('size')->once()->with('default')->andReturn(0);
    Queue::shouldReceive('connection')->once()->with('sync')->andReturn($queue);

    config([
        'healthcheck.core' => ['queue' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['checks']['queue'])->toMatchArray([
        'status' => 'UP',
        'default_connection' => 'sync',
        'queue' => 'default',
        'queue_size' => 0,
    ]);
});

it('checks the logging output path', function () {
    $logger = Mockery::mock();
    $logger->shouldReceive('info')->once();
    Log::shouldReceive('stack')->once()->with(['single', 'daily'])->andReturn($logger);

    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['logging' => true],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['checks']['logging']['status'])->toBe('UP');
    expect(storage_path('logs/health_check.log'))->toBeFile();
});

it('reports a failed logging check when the log directory cannot be prepared', function () {
    File::shouldReceive('ensureDirectoryExists')->once()->andThrow(new RuntimeException('directory failed'));

    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['logging' => true],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('unhealthy');
    expect($result['checks']['logging']['status'])->toBe('DOWN');
});

it('checks disk space at the configured path', function () {
    config([
        'healthcheck.disk_space_path' => sys_get_temp_dir(),
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['disk-space' => true],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['checks']['disk-space']['status'])->toBe('UP');
    expect($result['checks']['disk-space']['path'])->toBe(sys_get_temp_dir());
});
