<?php

namespace App\Services\Admin;

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
        $this->registerCheck('php_version', fn() => $this->checkPhpVersion());
        $this->registerCheck('memory', fn() => $this->checkMemory());
        $this->registerCheck('disk', fn() => $this->checkDisk());
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
     * PHP version check
     */
    private function checkPhpVersion(): array
    {
        $requiredVersion = '8.2.0';
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
            $lastMigration = db()->fetch(
                "SELECT * FROM migrations ORDER BY id DESC LIMIT 1"
            );

            if ($lastMigration) {
                return [
                    'status' => 'ok',
                    'message' => 'Last migration: ' . $lastMigration['basename'] . ' (' . $lastMigration['created_at'] . ')',
                    'last_migration' => $lastMigration['basename'],
                    'last_run' => $lastMigration['created_at'],
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
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
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
