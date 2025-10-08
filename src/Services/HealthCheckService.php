<?php /** @noinspection PhpUndefinedFunctionInspection */

namespace Masgeek\HealthCheck\Services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

class HealthCheckService
{
    public function run(): array
    {
        $enabledChecks = collect([
            'core' => config('healthcheck.core', []),
            'infrastructure' => config('healthcheck.infrastructure', []),
        ])->flatMap(fn($group) => $group);

        $availableChecks = [
            'env-config' => fn() => $this->checkEnvironmentConfig(),
            'database' => fn() => $this->checkDatabase(),
            'redis' => fn() => $this->checkRedis(),
            'cache' => fn() => $this->checkCache(),
            'storage' => fn() => $this->checkFileStorage(),
            'queue' => fn() => $this->checkQueue(),
            'mail' => fn() => $this->checkMailConnection(),
            'disk-space' => fn() => $this->checkDiskSpace(),
            'migrations' => fn() => $this->checkMigrations(),
            'php-extensions' => fn() => $this->checkPHPExtensions(),
            'loki' => fn() => $this->checkLoki(),
            'logging' => fn() => $this->checkLogging(),
        ];

        $results = [];

        foreach ($availableChecks as $key => $callback) {
            if (!empty($enabledChecks[$key])) {
                $results[$key] = $callback();
            }
        }

        $overallStatus = collect($results)
                ->isNotEmpty() && collect($results)->every(fn($r) => ($r['status'] ?? '') === 'UP');

        return [
            'status' => $overallStatus ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(),
            'checks' => $results,
        ];
    }


    private function checkDatabase(): array
    {
        try {
            $connection = DB::connection();
            $databaseName = $connection->getDatabaseName();
            $platform = $connection->getDriverName();

            $schema = config('database.connections.' . $connection->getName() . '.schema');

            $tableCount = DB::table('information_schema.tables')
                ->where('table_schema', $schema)
                ->count();

            return [
                'status' => 'UP',
                'database' => $databaseName,
                'schema' => $schema,
                'database_type' => $platform,
                'total_tables' => $tableCount,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }


    private function checkRedis(): array
    {
        try {
            $redis = Redis::connection();
            $info = $redis->info();
            $serviceName = $info['executable'] ?? 'NA';

            $memory = Arr::get($info, 'Memory', $info);

            return [
                'status' => 'UP',
                'version' => $info['redis_version'],
                'service' => $serviceName,
                //                'ping' => $ping,
                'memory' => [
                    'used' => $memory['used_memory_human'] ?? 'NA',
                    'peak' => $memory['used_memory_peak_human'] ?? 'NA',
                ],
                //                'info' => $info,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        try {
            $testKey = 'health_check_' . uniqid();
            Cache::put($testKey, 'test', 60);
            $value = Cache::get($testKey);
            Cache::forget($testKey);

            return [
                'status' => $value === 'test' ? 'UP' : 'DOWN',
                'driver' => Cache::getDefaultDriver(),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkFileStorage(): array
    {
        try {
            $testFile = 'health_check_' . uniqid() . '.txt';
            Storage::put($testFile, 'Storage health check');
            $fileExists = Storage::exists($testFile);
            Storage::delete($testFile);

            return [
                'status' => $fileExists ? 'UP' : 'DOWN',
                'default_disk' => config('filesystems.default'),
                'root_path' => Storage::getConfig(),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkQueue(): array
    {
        try {
            $defaultQueue = config('queue.default');

            return [
                'status' => 'UP',
                'default_connection' => $defaultQueue,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkMailConnection(): array
    {
        try {
            $transport = Mail::getSymfonyTransport();

            return [
                'status' => 'UP',
                'transport' => $transport,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        $percentage = round((1 - $free / $total) * 100, 2);

        return [
            'status' => $percentage > 90 ? 'DOWN' : 'UP',
            'total_space' => $this->formatBytes($total, 2),
            'free_space' => $this->formatBytes($free, 2),
            'used_percentage' => "{$percentage}%",
        ];

    }

    private function formatBytes(int $bytes, int $precision = 0): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB', 'PB'];
        $bytes = max($bytes, 0);
        $power = $bytes > 0 ? floor(log($bytes, 1024)) : 0;
        $power = min($power, count($units) - 1);
        $bytes /= pow(1024, $power);
        return round($bytes, $precision) . ' ' . $units[$power];
    }


    /** @noinspection SqlResolve
     * @noinspection SqlNoDataSourceInspection
     */
    private function checkMigrations(): array
    {
        $table = config('database.migrations.table');
        try {
            $pendingMigrations = DB::select("SELECT * FROM $table");
            return [
                'status' => count($pendingMigrations) > 0 ? 'UP' : 'DOWN',
                'table_name' => $table,
                'total_migrations' => count($pendingMigrations),
            ];
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'table_name' => $table,
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkEnvironmentConfig(): array
    {
        $inDebugMode = config('app.debug');
        return [
            'status' => $inDebugMode ? 'DOWN' : 'UP',
            'debug_mode' => $inDebugMode,
            'timezone' => config('app.timezone'),
        ];
    }

    private function checkPHPExtensions(): array
    {
        $requiredExtensions = [
            'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath',
        ];

        $extensionStatus = [];
        foreach ($requiredExtensions as $ext) {
            $extensionStatus[$ext] = extension_loaded($ext);
        }

        return $extensionStatus;
    }

    private function checkLoki(): array
    {
        try {
            $url = config('logging.channels.loki.with.url'); // e.g. http://loki:3100/loki/api/v1/status/buildinfo
            if (!$url) {
                return ['status' => 'DOWN', 'error' => 'Loki URL not configured'];
            }

            $url = rtrim($url, '/')
                . '/loki/api/v1/status/buildinfo';
            $status = [
                'status' => 'UP',
                'url' => $url,
            ];

            $response = Http::timeout(3)->get($url);

            if (!$response->ok()) {
                $status['status'] = 'DOWN';
                $status['error'] = 'Loki unreachable';
            } else {
                $data = $response->json();
                $status['build_date'] = $data['buildDate'] ?? 'unknown';
                $status['version'] = $data['version'] ?? 'unknown';
                $status['go_version'] = $data['go_version'] ?? 'unknown';
            }

            return $status;
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkLogging(): array
    {
        try {
            $logPath = storage_path('logs/health_check.log');
            $message = '[' . now()->toIso8601String() . '] Health check log test';
            file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND);

            Log::stack(['single', 'daily'])->info($message);

            return [
                'status' => 'UP',
                'log_path' => $logPath,
            ];
        } catch (Exception $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }
}
