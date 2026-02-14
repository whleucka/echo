<?php

namespace App\Services\Admin;

use App\Models\Migration;

class SystemHealthService
{
    private array $checks = [];

    public function __construct()
    {
        $this->registerDefaultChecks();
    }

    /**
     * Register default health checks
     */
    private function registerDefaultChecks(): void
    {
        $this->registerCheck('database', fn() => $this->checkDatabase());
        $this->registerCheck('redis', fn() => $this->checkRedis());
        $this->registerCheck('php_version', fn() => $this->checkPhpVersion());
        $this->registerCheck('memory', fn() => $this->checkMemory());
        $this->registerCheck('disk', fn() => $this->checkDisk());
        $this->registerCheck('uptime', fn() => $this->checkUptime());
        $this->registerCheck('extensions', fn() => $this->checkExtensions());
        $this->registerCheck('writable_dirs', fn() => $this->checkWritableDirs());
        $this->registerCheck('migrations', fn() => $this->checkMigrations());
    }

    /**
     * Register a custom health check
     */
    public function registerCheck(string $name, callable $check): void
    {
        $this->checks[$name] = $check;
    }

    /**
     * Run all health checks
     */
    public function runAllChecks(): array
    {
        $results = [];
        foreach ($this->checks as $name => $check) {
            $results[$name] = $this->runCheck($name, $check);
        }
        return $results;
    }

    /**
     * Run a single health check
     */
    public function runCheck(string $name, callable $check): array
    {
        $startTime = microtime(true);

        try {
            $result = $check();
            $result['duration_ms'] = round((microtime(true) - $startTime) * 1000, 2);
            return $result;
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2),
            ];
        }
    }

    /**
     * Get a single check by name
     */
    public function getCheck(string $name): ?array
    {
        if (!isset($this->checks[$name])) {
            return null;
        }
        return $this->runCheck($name, $this->checks[$name]);
    }

    /**
     * Get overall system status
     */
    public function getOverallStatus(): string
    {
        $results = $this->runAllChecks();
        $status = 'ok';

        foreach ($results as $result) {
            if ($result['status'] === 'error') {
                return 'error';
            }
            if ($result['status'] === 'warning') {
                $status = 'warning';
            }
        }

        return $status;
    }

    /**
     * Get status as JSON for monitoring tools
     */
    public function getStatusJson(): array
    {
        $checks = $this->runAllChecks();
        $overall = $this->getOverallStatus();

        return [
            'status' => $overall,
            'timestamp' => date('c'),
            'checks' => $checks,
        ];
    }

    /**
     * Database connectivity check
     */
    private function checkDatabase(): array
    {
        try {
            $result = db()->execute("SELECT 1 as ok")->fetchColumn();
            return [
                'status' => $result ? 'ok' : 'error',
                'message' => $result ? 'Database connection successful' : 'Database query failed',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'message' => 'Database connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Redis connectivity check
     */
    private function checkRedis(): array
    {
        try {
            $redis = \Echo\Framework\Redis\RedisManager::getInstance();

            if (!$redis->isAvailable()) {
                return [
                    'status' => 'warning',
                    'message' => 'Redis is not available',
                ];
            }

            $connection = $redis->connection('default');
            $info = $connection->info();
            $version = $info['redis_version'] ?? 'unknown';
            $uptime = (int)($info['uptime_in_seconds'] ?? 0);
            $uptimeStr = $this->formatUptime($uptime);

            return [
                'status' => 'ok',
                'message' => "Connected (v$version, up $uptimeStr)",
                'version' => $version,
                'uptime' => $uptime,
            ];
        } catch (\Throwable $e) {
            return [
                'status' => 'warning',
                'message' => 'Redis connection failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Format uptime seconds to human readable
     */
    private function formatUptime(int $seconds): string
    {
        if ($seconds < 60) {
            return $seconds . 's';
        }
        if ($seconds < 3600) {
            return floor($seconds / 60) . 'm';
        }
        if ($seconds < 86400) {
            return floor($seconds / 3600) . 'h ' . floor(($seconds % 3600) / 60) . 'm';
        }
        return floor($seconds / 86400) . 'd ' . floor(($seconds % 86400) / 3600) . 'h';
    }

    /**
     * PHP version check
     */
    private function checkPhpVersion(): array
    {
        $requiredVersion = '8.4.0';
        $currentVersion = PHP_VERSION;
        $isOk = version_compare($currentVersion, $requiredVersion, '>=');

        return [
            'status' => $isOk ? 'ok' : 'error',
            'message' => "PHP $currentVersion" . ($isOk ? '' : " (requires >= $requiredVersion)"),
            'current' => $currentVersion,
            'required' => $requiredVersion,
        ];
    }

    /**
     * Memory usage check
     */
    private function checkMemory(): array
    {
        $usage = memory_get_usage(true);
        $limit = $this->parseBytes(ini_get('memory_limit'));
        $percent = $limit > 0 ? round(($usage / $limit) * 100, 1) : 0;

        $status = 'ok';
        if ($percent >= 90) {
            $status = 'error';
        } elseif ($percent >= 70) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'message' => $this->formatBytes($usage) . ' / ' . ini_get('memory_limit') . " ($percent%)",
            'usage' => $usage,
            'limit' => $limit,
            'percent' => $percent,
        ];
    }

    /**
     * Disk space check
     */
    private function checkDisk(): array
    {
        $path = '/';
        $free = disk_free_space($path);
        $total = disk_total_space($path);
        $used = $total - $free;
        $percent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

        $status = 'ok';
        if ($percent >= 95) {
            $status = 'error';
        } elseif ($percent >= 80) {
            $status = 'warning';
        }

        return [
            'status' => $status,
            'message' => $this->formatBytes($free) . ' free of ' . $this->formatBytes($total) . " ($percent% used)",
            'free' => $free,
            'total' => $total,
            'used' => $used,
            'percent' => $percent,
        ];
    }

    /**
     * System uptime check (Linux only)
     */
    private function checkUptime(): array
    {
        if (PHP_OS_FAMILY !== 'Linux' || !file_exists('/proc/uptime')) {
            return [
                'status' => 'warning',
                'message' => 'Uptime not available on this platform',
            ];
        }

        $uptime = file_get_contents('/proc/uptime');
        $seconds = (int)explode(' ', $uptime)[0];
        $formatted = $this->formatUptime($seconds);

        return [
            'status' => 'ok',
            'message' => "System up $formatted",
            'uptime_seconds' => $seconds,
            'uptime_formatted' => $formatted,
        ];
    }

    /**
     * Required PHP extensions check
     */
    private function checkExtensions(): array
    {
        $required = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
        $missing = [];

        foreach ($required as $ext) {
            if (!extension_loaded($ext)) {
                $missing[] = $ext;
            }
        }

        if (empty($missing)) {
            return [
                'status' => 'ok',
                'message' => 'All required extensions loaded',
                'required' => $required,
            ];
        }

        return [
            'status' => 'error',
            'message' => 'Missing extensions: ' . implode(', ', $missing),
            'required' => $required,
            'missing' => $missing,
        ];
    }

    /**
     * Writable directories check
     */
    private function checkWritableDirs(): array
    {
        $root = config('paths.root');
        $dirs = [
            'uploads' => config('paths.uploads') ?? $root . 'storage/uploads',
            'cache' => config('paths.template_cache') ?? $root . 'templates/.cache',
            'logs' => config('paths.logs') ?? $root . 'storage/logs',
        ];

        $issues = [];
        foreach ($dirs as $name => $path) {
            if (!is_dir($path)) {
                $issues[] = "$name directory does not exist ($path)";
            } elseif (!is_writable($path)) {
                $issues[] = "$name directory is not writable ($path)";
            }
        }

        if (empty($issues)) {
            return [
                'status' => 'ok',
                'message' => 'All directories writable',
                'directories' => array_keys($dirs),
            ];
        }

        return [
            'status' => 'warning',
            'message' => implode('; ', $issues),
            'issues' => $issues,
        ];
    }

    /**
     * Migration status check
     */
    private function checkMigrations(): array
    {
        try {
            $lastMigration = Migration::where('id', '>', '0')
                ->orderBy('id', 'DESC')
                ->first();

            if ($lastMigration) {
                return [
                    'status' => 'ok',
                    'message' => 'Last migration: ' . $lastMigration->basename . ' (' . $lastMigration->created_at . ')',
                    'last_migration' => $lastMigration->basename,
                    'last_run' => $lastMigration->created_at,
                ];
            }

            return [
                'status' => 'warning',
                'message' => 'No migrations found',
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'warning',
                'message' => 'Could not check migrations: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Format bytes to human readable
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        return format_bytes($bytes, $precision);
    }

    /**
     * Parse bytes from PHP ini format
     */
    private function parseBytes(string $value): int
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int)$value;

        switch ($last) {
            case 'g':
                $value *= 1024;
            case 'm':
                $value *= 1024;
            case 'k':
                $value *= 1024;
        }

        return $value;
    }
}
