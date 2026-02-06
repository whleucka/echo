<?php

namespace Echo\Framework\Admin\Widgets;

use Echo\Framework\Admin\Widget;
use Echo\Framework\Redis\RedisManager;

class RedisWidget extends Widget
{
    protected string $id = 'redis';
    protected string $title = 'Redis';
    protected string $icon = 'database';
    protected string $template = 'admin/widgets/redis.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 30;
    protected int $cacheTtl = 0; // Don't cache Redis stats
    protected int $priority = 60;

    public function getData(): array
    {
        $stats = [];
        $status = 'unavailable';

        try {
            $redis = RedisManager::getInstance();

            if (!$redis->isAvailable()) {
                return [
                    'status' => 'unavailable',
                    'stats' => [],
                    'message' => 'Redis is not available',
                ];
            }

            $connection = $redis->connection('default');
            $info = $connection->info();

            $status = 'ok';

            // Server info
            $stats['version'] = [
                'label' => 'Redis Version',
                'value' => $info['redis_version'] ?? 'Unknown',
                'icon' => 'tag',
            ];

            // Uptime
            $uptimeSeconds = (int)($info['uptime_in_seconds'] ?? 0);
            $stats['uptime'] = [
                'label' => 'Uptime',
                'value' => $this->formatUptime($uptimeSeconds),
                'icon' => 'clock',
            ];

            // Memory usage
            $usedMemory = (int)($info['used_memory'] ?? 0);
            $maxMemory = (int)($info['maxmemory'] ?? 0);
            $memoryPercent = $maxMemory > 0 ? round(($usedMemory / $maxMemory) * 100, 1) : 0;
            $stats['memory'] = [
                'label' => 'Memory Usage',
                'value' => $this->formatBytes($usedMemory) . ($maxMemory > 0 ? ' / ' . $this->formatBytes($maxMemory) : ''),
                'icon' => 'memory',
                'percent' => $memoryPercent,
                'status' => $memoryPercent < 70 ? 'ok' : ($memoryPercent < 90 ? 'warning' : 'error'),
            ];

            // Connected clients
            $stats['clients'] = [
                'label' => 'Connected Clients',
                'value' => $info['connected_clients'] ?? '0',
                'icon' => 'people',
            ];

            // Key count (from keyspace info)
            $totalKeys = 0;
            foreach ($info as $key => $value) {
                if (str_starts_with($key, 'db') && is_string($value)) {
                    if (preg_match('/keys=(\d+)/', $value, $matches)) {
                        $totalKeys += (int)$matches[1];
                    }
                }
            }
            $stats['keys'] = [
                'label' => 'Total Keys',
                'value' => number_format($totalKeys),
                'icon' => 'key',
            ];

            // Cache hit ratio
            $hits = (int)($info['keyspace_hits'] ?? 0);
            $misses = (int)($info['keyspace_misses'] ?? 0);
            $total = $hits + $misses;
            $hitRatio = $total > 0 ? round(($hits / $total) * 100, 1) : 0;
            $stats['hit_ratio'] = [
                'label' => 'Cache Hit Ratio',
                'value' => $hitRatio . '%',
                'icon' => 'bullseye',
                'percent' => $hitRatio,
                'status' => $hitRatio >= 90 ? 'ok' : ($hitRatio >= 70 ? 'warning' : 'error'),
            ];

            // Check for issues
            if (isset($stats['memory']['status']) && $stats['memory']['status'] === 'error') {
                $status = 'warning';
            }

        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'stats' => [],
                'message' => 'Error connecting to Redis: ' . $e->getMessage(),
            ];
        }

        return [
            'status' => $status,
            'stats' => $stats,
            'message' => null,
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
}
