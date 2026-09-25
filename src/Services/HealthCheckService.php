<?php

/** @noinspection PhpUndefinedFunctionInspection */

namespace Masgeek\HealthCheck\Services;

use Exception;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;
use Throwable;

class HealthCheckService
{
    public function run(): array
    {
        $enabledChecks = collect([
            config('healthcheck.core', []),
            config('healthcheck.infrastructure', []),
        ])->filter(fn ($group) => is_array($group))
            ->flatMap(fn (array $group) => $group)
            ->filter(fn ($enabled) => (bool) $enabled);

        $availableChecks = [
            'env-config' => fn () => $this->checkEnvironmentConfig(),
            'database' => fn () => $this->checkDatabase(),
            'redis' => fn () => $this->checkRedis(),
            'cache' => fn () => $this->checkCache(),
            'storage' => fn () => $this->checkFileStorage(),
            'queue' => fn () => $this->checkQueue(),
            'mail' => fn () => $this->checkMailConnection(),
            'disk-space' => fn () => $this->checkDiskSpace(),
            'migrations' => fn () => $this->checkMigrations(),
            'php-extensions' => fn () => $this->checkPHPExtensions(),
            'loki' => fn () => $this->checkLoki(),
            'logging' => fn () => $this->checkLogging(),
            'failed-jobs' => fn () => $this->checkFailedJobs(),
            'outbound' => fn () => $this->checkOutbound(),
            'certificate' => fn () => $this->checkCertificate(),
            'config-cache' => fn () => $this->checkConfigCache(),
            'build' => fn () => $this->checkBuild(),
        ];

        $results = [];

        foreach ($availableChecks as $key => $callback) {
            if (!empty($enabledChecks[$key])) {
                $results[$key] = $callback();
            }
        }

        $overallStatus = collect($results)
                ->isNotEmpty() && collect($results)->every(fn ($r) => ($r['status'] ?? '') === 'UP');

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

            $tableCount = match ($platform) {
                'mysql', 'mariadb' => $connection->table('information_schema.tables')
                    ->where('table_schema', $schema)
                    ->count(),
                'pgsql' => $connection->table('pg_catalog.pg_tables')
                    ->whereRaw('schemaname = coalesce(?, current_schema())', [$schema])
                    ->count(),
                'sqlite' => $connection->table('sqlite_master')
                    ->where('type', 'table')
                    ->where('name', 'not like', 'sqlite_%')
                    ->count(),
                'sqlsrv' => $connection->table('sys.tables')
                    ->when($schema, fn ($query) => $query->whereRaw('schema_id = (select schema_id from sys.schemas where name = ?)', [$schema]))
                    ->count(),
                default => throw new Exception("Unsupported database driver: {$platform}"),
            };

            return [
                'status' => 'UP',
                'database' => $databaseName,
                'schema' => $schema,
                'database_type' => $platform,
                'total_tables' => $tableCount,
            ];
        } catch (Throwable $e) {
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
                'status' => isset($info['redis_version']) ? 'UP' : 'DOWN',
                'version' => $info['redis_version'] ?? null,
                'service' => $serviceName,
                //                'ping' => $ping,
                'memory' => [
                    'used' => $memory['used_memory_human'] ?? 'NA',
                    'peak' => $memory['used_memory_peak_human'] ?? 'NA',
                ],
                //                'info' => $info,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCache(): array
    {
        $testKey = 'health_check_' . uniqid('', true);

        try {
            Cache::put($testKey, 'test', 60);
            $value = Cache::get($testKey);

            return [
                'status' => $value === 'test' ? 'UP' : 'DOWN',
                'driver' => Cache::getDefaultDriver(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        } finally {
            try {
                Cache::forget($testKey);
            } catch (Throwable) {
            }
        }
    }

    private function checkFileStorage(): array
    {
        $testFile = 'health_check_' . uniqid('', true) . '.txt';

        try {
            Storage::put($testFile, 'Storage health check');
            $fileExists = Storage::exists($testFile);

            return [
                'status' => $fileExists ? 'UP' : 'DOWN',
                'default_disk' => config('filesystems.default'),
                'root_path' => Storage::getConfig(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        } finally {
            try {
                Storage::delete($testFile);
            } catch (Throwable) {
            }
        }
    }

    private function checkQueue(): array
    {
        try {
            $defaultQueue = config('queue.default');
            $queueName = config("queue.connections.{$defaultQueue}.queue", 'default');
            $queueSize = Queue::connection($defaultQueue)->size($queueName);

            return [
                'status' => $queueSize >= 0 ? 'UP' : 'DOWN',
                'default_connection' => $defaultQueue,
                'queue' => $queueName,
                'queue_size' => $queueSize,
            ];
        } catch (Throwable $e) {
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
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkDiskSpace(): array
    {
        try {
            $path = config('healthcheck.disk_space_path') ?: storage_path();
            $total = disk_total_space($path);
            $free = disk_free_space($path);

            if ($total === false || $free === false || $total <= 0) {
                throw new Exception('Unable to read disk space');
            }

            $percentage = round((1 - $free / $total) * 100, 2);

            return [
                'status' => $percentage > 90 ? 'DOWN' : 'UP',
                'path' => $path,
                'total_space' => $this->formatBytes($total, 2),
                'free_space' => $this->formatBytes($free, 2),
                'used_percentage' => "{$percentage}%",
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function formatBytes(int|float $bytes, int $precision = 0): string
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
        try {
            $migrator = app('migrator');
            $migrationFiles = $migrator->getMigrationFiles(database_path('migrations'));
            $ranMigrations = $migrator->getRepository()->getRan();
            $pendingMigrations = array_diff(array_keys($migrationFiles), $ranMigrations);

            return [
                'status' => count($pendingMigrations) === 0 ? 'UP' : 'DOWN',
                'total_migrations' => count($migrationFiles),
                'pending_migrations' => count($pendingMigrations),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
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
        $requiredExtensions = config('healthcheck.php_extensions', [
            'pdo', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'bcmath',
        ]);
        $extensionStatus = collect($requiredExtensions)
            ->filter(fn ($extension) => is_string($extension))
            ->mapWithKeys(fn (string $extension) => [$extension => extension_loaded($extension)])
            ->all();

        return [
            'status' => !in_array(false, $extensionStatus, true) ? 'UP' : 'DOWN',
            'extensions' => $extensionStatus,
        ];
    }

    private function checkLoki(): array
    {
        try {
            $url = config('healthcheck.services.loki_url');
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
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkFailedJobs(): array
    {
        try {
            $table = config('queue.failed.table', 'failed_jobs');
            if (!is_string($table) || !preg_match('/^[A-Za-z0-9_.-]+$/', $table)) {
                throw new Exception('Invalid failed jobs table name');
            }

            $connection = DB::connection();
            $query = $connection->table($table);
            $count = (clone $query)->count();
            $columns = $connection->getSchemaBuilder()->getColumnListing($table);
            $recent = null;
            $oldest = null;

            if (in_array('created_at', $columns, true)) {
                $recent = (clone $query)
                    ->where('created_at', '>=', now()->subMinutes((int) config('healthcheck.failed_jobs_recent_minutes', 60)))
                    ->count();
                $oldest = (clone $query)->orderBy('created_at')->value('created_at');
            }

            return [
                'status' => $count === 0 ? 'UP' : 'DOWN',
                'failed_jobs' => $count,
                'recent_failed_jobs' => $recent,
                'oldest_failed_at' => $oldest,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkOutbound(): array
    {
        try {
            $urls = collect(config('healthcheck.services.outbound_urls', []))
                ->filter(fn ($url) => is_string($url) && filter_var($url, FILTER_VALIDATE_URL))
                ->values();
            $timeout = (int) config('healthcheck.services.outbound_timeout', 3);

            if ($urls->isEmpty()) {
                return [
                    'status' => 'DOWN',
                    'error' => 'No outbound URLs configured',
                ];
            }

            $results = $urls->mapWithKeys(function (string $url) use ($timeout) {
                $response = Http::timeout($timeout)->get($url);

                return [$url => $response->ok()];
            });

            return [
                'status' => $results->every(fn (bool $reachable) => $reachable) ? 'UP' : 'DOWN',
                'services' => $results->all(),
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkCertificate(): array
    {
        try {
            $host = config('healthcheck.services.certificate_host');
            $port = (int) config('healthcheck.services.certificate_port', 443);
            $warningDays = (int) config('healthcheck.services.certificate_warning_days', 14);

            if (!is_string($host) || $host === '' || $port < 1 || $port > 65535) {
                throw new Exception('Invalid certificate host or port');
            }

            $context = stream_context_create([
                'ssl' => [
                    'capture_peer_cert' => true,
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                ],
            ]);
            $stream = fopen("ssl://{$host}:{$port}", 'rb', false, $context);
            if ($stream === false) {
                throw new Exception('Unable to connect to certificate host');
            }

            $certificate = stream_context_get_params($stream)['options']['ssl']['peer_certificate'] ?? null;
            fclose($stream);
            $parsed = is_resource($certificate) || is_object($certificate)
                ? openssl_x509_parse($certificate)
                : false;

            if (!is_array($parsed) || !isset($parsed['validTo_time_t'])) {
                throw new Exception('Unable to read TLS certificate');
            }

            $expiresAt = (int) $parsed['validTo_time_t'];
            $expiresInDays = (int) floor(($expiresAt - now()->getTimestamp()) / 86400);

            return [
                'status' => $expiresInDays > $warningDays ? 'UP' : 'DOWN',
                'host' => $host,
                'port' => $port,
                'expires_at' => date(DATE_ATOM, $expiresAt),
                'expires_in_days' => $expiresInDays,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }

    private function checkConfigCache(): array
    {
        $cached = app()->configurationIsCached();

        return [
            'status' => $cached ? 'UP' : 'DOWN',
            'cached' => $cached,
        ];
    }

    private function checkBuild(): array
    {
        $version = config('healthcheck.services.build_version');
        $commit = config('healthcheck.services.build_commit');
        $metadata = array_filter([
            'version' => $version,
            'commit' => $commit,
        ]);

        return [
            'status' => $metadata !== [] ? 'UP' : 'DOWN',
            'metadata' => $metadata,
        ];
    }

    private function checkLogging(): array
    {
        try {
            $logPath = storage_path('logs/health_check.log');
            $message = '[' . now()->toIso8601String() . '] Health check log test';
            File::ensureDirectoryExists(dirname($logPath));

            if (file_put_contents($logPath, $message . PHP_EOL, FILE_APPEND) === false) {
                throw new Exception('Unable to write health check log');
            }

            Log::stack(['single', 'daily'])->info($message);

            return [
                'status' => 'UP',
                'log_path' => $logPath,
            ];
        } catch (Throwable $e) {
            return [
                'status' => 'DOWN',
                'error' => $e->getMessage(),
            ];
        }
    }
}
