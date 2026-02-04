<?php

namespace Echo\Framework\Admin\Widgets;

use Echo\Framework\Admin\Widget;

class SystemHealthWidget extends Widget
{
    protected string $id = 'system-health';
    protected string $title = 'System Health';
    protected string $icon = 'heart-pulse';
    protected string $template = 'admin/widgets/system-health.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 120;
    protected int $cacheTtl = 60;

    public function getData(): array
    {
        $checks = [];

        // PHP Version check
        $checks['php'] = [
            'label' => 'PHP Version',
            'value' => PHP_VERSION,
            'status' => version_compare(PHP_VERSION, '8.2.0', '>=') ? 'ok' : 'error',
        ];

        // Memory check
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $memoryPercent = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 1) : 0;
        $checks['memory'] = [
            'label' => 'Memory',
            'value' => $this->formatBytes($memoryUsage) . ' / ' . ini_get('memory_limit'),
            'status' => $memoryPercent < 70 ? 'ok' : ($memoryPercent < 90 ? 'warning' : 'error'),
            'percent' => $memoryPercent,
        ];

        // Disk check
        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskPercent = $diskTotal > 0 ? round((($diskTotal - $diskFree) / $diskTotal) * 100, 1) : 0;
        $checks['disk'] = [
            'label' => 'Disk Space',
            'value' => $this->formatBytes($diskFree) . ' free',
            'status' => $diskPercent < 80 ? 'ok' : ($diskPercent < 95 ? 'warning' : 'error'),
            'percent' => $diskPercent,
        ];

        // Database check
        try {
            $dbCheck = db()->execute("SELECT 1")->fetchColumn();
            $checks['database'] = [
                'label' => 'Database',
                'value' => 'Connected',
                'status' => $dbCheck ? 'ok' : 'error',
            ];
        } catch (\Exception $e) {
            $checks['database'] = [
                'label' => 'Database',
                'value' => 'Error',
                'status' => 'error',
            ];
        }

        // Extensions check
        $requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring', 'openssl'];
        $missingExtensions = [];
        foreach ($requiredExtensions as $ext) {
            if (!extension_loaded($ext)) {
                $missingExtensions[] = $ext;
            }
        }
        $checks['extensions'] = [
            'label' => 'PHP Extensions',
            'value' => empty($missingExtensions) ? 'All loaded' : 'Missing: ' . implode(', ', $missingExtensions),
            'status' => empty($missingExtensions) ? 'ok' : 'error',
        ];

        // Overall status
        $overallStatus = 'ok';
        foreach ($checks as $check) {
            if ($check['status'] === 'error') {
                $overallStatus = 'error';
                break;
            }
            if ($check['status'] === 'warning') {
                $overallStatus = 'warning';
            }
        }

        return [
            'checks' => $checks,
            'overall_status' => $overallStatus,
        ];
    }

    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

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
