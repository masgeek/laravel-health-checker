<?php

use Masgeek\HealthCheck\Services\HealthCheckService;

function bindMigrationState(array $migrationFiles, array $ranMigrations): void
{
    $repository = Mockery::mock();
    $repository->shouldReceive('getRan')->andReturn($ranMigrations);

    $migrator = Mockery::mock();
    $migrator->shouldReceive('getMigrationFiles')->andReturn($migrationFiles);
    $migrator->shouldReceive('getRepository')->andReturn($repository);

    app()->instance('migrator', $migrator);
}

it('reports a fully migrated application as up', function () {
    bindMigrationState([
        '2026_01_01_000000_create_example' => '/database/migrations/example.php',
    ], [
        '2026_01_01_000000_create_example',
    ]);

    config([
        'healthcheck.core' => ['migrations' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('healthy');
    expect($result['checks']['migrations'])->toMatchArray([
        'status' => 'UP',
        'total_migrations' => 1,
        'pending_migrations' => 0,
    ]);
});

it('reports pending migrations as down', function () {
    bindMigrationState([
        '2026_01_01_000000_create_example' => '/database/migrations/example.php',
        '2026_01_02_000000_create_another' => '/database/migrations/another.php',
    ], [
        '2026_01_01_000000_create_example',
    ]);

    config([
        'healthcheck.core' => ['migrations' => true],
        'healthcheck.infrastructure' => [],
    ]);

    $result = (new HealthCheckService())->run();

    expect($result['status'])->toBe('unhealthy');
    expect($result['checks']['migrations'])->toMatchArray([
        'status' => 'DOWN',
        'total_migrations' => 2,
        'pending_migrations' => 1,
    ]);
});
