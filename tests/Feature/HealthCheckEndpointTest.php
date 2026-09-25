<?php

it('returns healthy status when enabled checks pass', function () {
    config([
        'healthcheck.core' => ['env-config' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('status', 'healthy')
        ->assertJsonPath('checks.env-config.status', 'UP');
});

it('hides detailed check data by default', function () {
    config([
        'healthcheck.core' => ['env-config' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $this->getJson('/health')
        ->assertOk()
        ->assertJsonMissingPath('checks.env-config.timezone');
});

it('exposes detailed check data when explicitly enabled', function () {
    config([
        'healthcheck.expose_details' => true,
        'healthcheck.core' => ['env-config' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $this->getJson('/health')
        ->assertOk()
        ->assertJsonPath('checks.env-config.timezone', config('app.timezone'));
});

it('returns an unhealthy JSON command exit code', function () {
    config([
        'healthcheck.core' => [],
        'healthcheck.infrastructure' => [],
    ]);

    $this->artisan('health:check --json')->assertExitCode(1);
});
