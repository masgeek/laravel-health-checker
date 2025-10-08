<?php /** @noinspection PhpUnused */

namespace Masgeek\HealthCheck;

use Illuminate\Support\ServiceProvider;

class HealthCheckServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishConfig();
        $this->loadRoutes();
    }

    public function register(): void
    {
        $this->mergeConfig();
    }

    protected function publishConfig(): void
    {
        $this->publishes([
            $this->packageConfigPath() => $this->app->configPath('healthcheck.php'),
        ], 'healthcheck-config');
    }

    protected function loadRoutes(): void
    {
        $this->loadRoutesFrom($this->packagePath('routes/web.php'));
    }

    protected function mergeConfig(): void
    {
        $this->mergeConfigFrom(
            $this->packageConfigPath(),
            'healthcheck'
        );
    }

    protected function packageConfigPath(): string
    {
        return $this->packagePath('config/healthcheck.php');
    }

    protected function packagePath(string $relative): string
    {
        return __DIR__ . '/../' . ltrim($relative, '/');
    }
}
