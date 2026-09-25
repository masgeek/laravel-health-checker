<?php

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Masgeek\HealthCheck\HealthCheckServiceProvider;

it('merges configuration and registers the health route', function () {
    expect(config('healthcheck.expose_details'))->toBeFalse();
    expect(app('router')->getRoutes()->getByName('health.check'))->not->toBeNull();
});

it('registers the console command', function () {
    expect(Artisan::all())->toHaveKey('health:check');
});

it('publishes the configuration under the documented tag', function () {
    $paths = ServiceProvider::pathsToPublish(HealthCheckServiceProvider::class, 'healthcheck-config');

    expect($paths)->not->toBeEmpty();
});
