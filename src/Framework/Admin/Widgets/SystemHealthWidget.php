<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\SystemHealthService;
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
    protected int $priority = 20;

    public function __construct(private SystemHealthService $healthService)
    {
    }

    public function getData(): array
    {
        $results = $this->healthService->runAllChecks();
        $checks = [];

        // Framework Version
        if (isset($results['framework_version'])) {
            $checks['framework'] = [
                'label' => 'Framework Version',
                'value' => $results['framework_version']['version'] ?? 'unknown',
                'status' => $results['framework_version']['status'],
            ];
        }

        // PHP Version
        if (isset($results['php_version'])) {
            $checks['php'] = [
                'label' => 'PHP Version',
                'value' => $results['php_version']['current'] ?? PHP_VERSION,
                'status' => $results['php_version']['status'],
            ];
        }

        // Memory
        if (isset($results['memory'])) {
            $checks['memory'] = [
                'label' => 'Memory',
                'value' => $results['memory']['message'] ?? '',
                'status' => $results['memory']['status'],
                'percent' => $results['memory']['percent'] ?? 0,
            ];
        }

        // Disk
        if (isset($results['disk'])) {
            $checks['disk'] = [
                'label' => 'Disk Space',
                'value' => isset($results['disk']['free'])
                    ? format_bytes($results['disk']['free']) . ' free'
                    : ($results['disk']['message'] ?? ''),
                'status' => $results['disk']['status'],
                'percent' => $results['disk']['percent'] ?? 0,
            ];
        }

        // Database
        if (isset($results['database'])) {
            $checks['database'] = [
                'label' => 'Database',
                'value' => $results['database']['status'] === 'ok' ? 'Connected' : 'Error',
                'status' => $results['database']['status'],
            ];
        }

        // Redis
        if (isset($results['redis'])) {
            $checks['redis'] = [
                'label' => 'Redis',
                'value' => match ($results['redis']['status']) {
                    'ok' => 'Connected',
                    'warning' => 'Unavailable',
                    default => 'Error',
                },
                'status' => $results['redis']['status'],
            ];
        }

        // Extensions
        if (isset($results['extensions'])) {
            $checks['extensions'] = [
                'label' => 'PHP Extensions',
                'value' => $results['extensions']['status'] === 'ok'
                    ? 'All loaded'
                    : ($results['extensions']['message'] ?? 'Missing extensions'),
                'status' => $results['extensions']['status'],
            ];
        }

        // System Uptime
        if (isset($results['uptime'])) {
            $checks['uptime'] = [
                'label' => 'System Uptime',
                'value' => $results['uptime']['uptime_formatted'] ?? 'N/A',
                'status' => $results['uptime']['status'],
            ];
        }

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
}
