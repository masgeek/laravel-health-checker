<?php

namespace Masgeek\HealthCheck\Commands;


use Illuminate\Console\Command;
use Masgeek\HealthCheck\Services\HealthCheckService;

class CheckHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * Example:
     *   php artisan health:check
     *
     * Optional flags:
     *   --json : Output in JSON
     *
     * @var string
     */
    protected $signature = 'health:check {--json : Output as JSON response}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Run all configured health checks and show system status';

    public function handle(): int
    {
        $this->info('🔍 Running system health checks...');
        $service = new HealthCheckService();
        $result = $service->run();

        if ($this->option('json')) {
            $this->line(json_encode($result, JSON_PRETTY_PRINT));
            return 0;
        }

        $overallStatus = $result['status'] ?? 'unknown';
        $this->line('');
        $this->line('System Status: ' . ($overallStatus === 'healthy' ? '✅ HEALTHY' : '❌ UNHEALTHY'));
        $this->line('Timestamp: ' . $result['timestamp']);
        $this->line('');

        foreach ($result['checks'] as $name => $check) {
            $status = $check['status'] ?? 'N/A';
            $statusEmoji = $status === 'UP' ? '🟢' : '🔴';
            $this->line(sprintf("%s %-15s %s", $statusEmoji, ucfirst($name), $status));

            if (isset($check['error'])) {
                $this->line("   ↳ Error: " . $check['error']);
            }
        }

        return $overallStatus === 'healthy' ? 0 : 1;
    }
}
