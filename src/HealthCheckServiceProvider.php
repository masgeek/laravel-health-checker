<?php /** @noinspection PhpUnused */

namespace Masgeek\HealthCheck;

use Illuminate\Support\ServiceProvider;
use Masgeek\HealthCheck\Commands\CheckHealthCommand;

class HealthCheckServiceProvider extends ServiceProvider
{
    public function boot(): void
    {


        $this->publishes([
            __DIR__ . '/config/healthcheck.php' => $this->app->configPath('healthcheck.php'),
        ], 'healthcheck-config');


        $this->loadRoutesFrom(__DIR__ . '/routes/web.php');

        if ($this->app->runningInConsole()) {
            $this->commands([
                CheckHealthCommand::class,
            ]);
        }
    }

    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/config/healthcheck.php',
            'healthcheck'
        );
    }
}
