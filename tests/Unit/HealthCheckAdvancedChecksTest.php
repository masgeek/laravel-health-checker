<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Masgeek\HealthCheck\Services\HealthCheckService;

it('reports failed queue jobs', function () {
    Schema::create('failed_jobs', function (Blueprint $table) {
        $table->id();
        $table->timestamp('created_at')->nullable();
    });
    DB::table('failed_jobs')->insert(['created_at' => now()]);

    config([
        'healthcheck.core' => ['failed-jobs' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('unhealthy');
    expect($result['checks']['failed-jobs'])->toMatchArray([
        'status' => 'DOWN',
        'failed_jobs' => 1,
        'recent_failed_jobs' => 1,
    ]);
});

it('checks configured outbound dependencies', function () {
    Http::fake([
        'https://api.example.test/*' => Http::response([], 200),
        'https://unavailable.example.test/*' => Http::response([], 503),
    ]);

    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['outbound' => true],
        'healthcheck.services.outbound_urls' => [
            'https://api.example.test/health',
            'https://unavailable.example.test/health',
        ],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('unhealthy');
    expect($result['checks']['outbound']['services'])->toBe([
        'https://api.example.test/health' => true,
        'https://unavailable.example.test/health' => false,
    ]);
});

it('reports build metadata when available', function () {
    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['build' => true],
        'healthcheck.services.build_version' => '1.2.3',
        'healthcheck.services.build_commit' => 'abc123',
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('healthy');
    expect($result['checks']['build']['metadata'])->toBe([
        'version' => '1.2.3',
        'commit' => 'abc123',
    ]);
});

it('reports the configuration cache state', function () {
    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['config-cache' => true],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['checks']['config-cache']['cached'])->toBeFalse();
    expect($result['checks']['config-cache']['status'])->toBe('DOWN');
});

it('fails certificate checks when the endpoint is not configured', function () {
    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => ['certificate' => true],
        'healthcheck.services.certificate_host' => null,
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('unhealthy');
    expect($result['checks']['certificate']['status'])->toBe('DOWN');
});
