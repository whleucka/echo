<?php

namespace App\Services\Admin;

class DashboardService
{
    public function getTotalSales(): string
    {
        return '$' . number_format(0, 2);
    }

    public function getTodaySales(): string
    {
        return '$' . number_format(0, 2);
    }

    public function getUsersCount(): int
    {
        return db()->execute(
            "SELECT count(*) FROM users"
        )->fetchColumn();
    }

    public function getActiveUsersCount(): int
    {
        return db()->execute(
            "SELECT COUNT(DISTINCT user_id) AS active_users
            FROM sessions
            WHERE created_at >= NOW() - INTERVAL 30 MINUTE"
        )->fetchColumn();
    }

    public function getCustomersCount(): int
    {
        return 0;
    }

    public function getNewCustomersCount(): int
    {
        return 0;
    }

    public function getModulesCount(): int
    {
        return db()->execute(
            "SELECT count(*) FROM modules WHERE parent_id IS NOT NULL"
        )->fetchColumn();
    }

    public function getTotalRequests(): int
    {
        return db()->execute(
            "SELECT count(*) FROM sessions"
        )->fetchColumn();
    }

    public function getTodayRequests(): int
    {
        return db()->execute(
            "SELECT count(*) FROM sessions WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();
    }

    public function getTotalRequestsChart(): array
    {
        return [];
    }

    public function getTodayRequestsChart(): array
    {
        $data = db()->fetchAll(
            "SELECT
                HOUR(created_at) AS hour,
                COUNT(*) AS total
            FROM sessions
            WHERE DATE(created_at) = CURDATE()
            GROUP BY HOUR(created_at)
            ORDER BY hour"
        );

        $hours = range(0, 23);
        $payload = array_fill(0, 24, 0);

        foreach ($data as $row) {
            $payload[(int)$row['hour']] = (int)$row['total'];
        }

        $labels = array_map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . ":00", $hours);
        $today = date("l, F d, Y");

        return [
            'id' => 'requests-chart-today',
            'title' => "Today's Requests",
            'icon' => 'clock',
            'refresh_url' => '/admin/dashboard/requests/chart/today',
            'options' => json_encode([
                'type' => 'line',
                'data' => (object)[
                    'labels' => $labels,
                    'datasets' => [
                        (object)[
                            'label' => "$today",
                            'data' => $payload,
                            'fill' => false,
                            'backgroundColor' => 'rgba(0, 94, 255, 0.5)',
                            'borderColor' => 'rgb(0, 94, 255)',
                            'borderWidth' => 2,
                            'tension' => 0.1,
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                        ]
                    ],
                ],
            ]),
        ];
    }

    public function getWeekRequestsChart(): array
    {
        $data = db()->fetchAll(
            "SELECT
                MIN(DAYNAME(created_at)) AS day_name,
                DATE(created_at) AS day_date,
                COUNT(*) AS total
            FROM sessions
            WHERE YEARWEEK(created_at, 1) = YEARWEEK(CURDATE(), 1)
            GROUP BY day_date
            ORDER BY day_date"
        );

        $labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $payload = array_fill(0, 7, 0);

        foreach ($data as $row) {
            $index = array_search($row['day_name'], $labels);
            if ($index !== false) {
                $payload[$index] = (int)$row['total'];
            }
        }

        $week = date("W");

        return [
            'id' => 'requests-chart-week',
            'title' => 'This Week',
            'icon' => 'calendar-week',
            'refresh_url' => '/admin/dashboard/requests/chart/week',
            'options' => json_encode([
                'type' => 'bar',
                'data' => (object)[
                    'labels' => $labels,
                    'datasets' => [
                        (object)[
                            'label' => "Week $week",
                            'data' => $payload,
                            'fill' => false,
                            'backgroundColor' => 'rgba(255, 159, 64, 0.5)',
                            'borderColor' => 'rgb(255, 159, 64)',
                            'borderWidth' => 2,
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                        ]
                    ],
                ],
            ]),
        ];
    }

    public function getMonthRequestsChart(): array
    {
        $data = db()->fetchAll(
            "SELECT
                DAY(created_at) AS day_number,
                COUNT(*) AS total
            FROM sessions
            WHERE YEAR(created_at) = YEAR(CURDATE()) AND
                MONTH(created_at) = MONTH(CURDATE())
            GROUP BY day_number
            ORDER BY day_number"
        );

        $daysInMonth = date('t');
        $labels = range(1, $daysInMonth);
        $payload = array_fill(0, $daysInMonth, 0);

        foreach ($data as $row) {
            $payload[$row['day_number'] - 1] = (int)$row['total'];
        }

        $month = date('F, Y');

        return [
            'id' => 'requests-chart-month',
            'title' => 'This Month',
            'icon' => 'calendar-month',
            'refresh_url' => '/admin/dashboard/requests/chart/month',
            'options' => json_encode([
                'type' => 'bar',
                'data' => (object)[
                    'labels' => $labels,
                    'datasets' => [
                        (object)[
                            'label' => "$month",
                            'data' => $payload,
                            'fill' => false,
                            'backgroundColor' => 'rgba(153, 102, 255, 0.5)',
                            'borderColor' => 'rgb(153, 102, 255)',
                            'borderWidth' => 2,
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                        ]
                    ],
                ],
            ]),
        ];
    }

    public function getYTDRequestsChart(): array
    {
        $data = db()->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS total
            FROM sessions
            WHERE created_at >= DATE_FORMAT(CURDATE(), '%Y-01-01')
            GROUP BY month
            ORDER BY month"
        );

        $labels = [];
        $payload = [];

        foreach ($data as $row) {
            $labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $payload[] = (int)$row['total'];
        }

        $year = date("Y");

        return [
            'id' => 'requests-chart-ytd',
            'title' => 'Year to Date',
            'icon' => 'calendar-range',
            'refresh_url' => '/admin/dashboard/requests/chart/ytd',
            'options' => json_encode([
                'type' => 'bar',
                'data' => (object)[
                    'labels' => $labels,
                    'datasets' => [
                        (object)[
                            'label' => "$year",
                            'data' => $payload,
                            'fill' => false,
                            'backgroundColor' => 'rgba(91, 235, 52, 0.5)',
                            'borderColor' => 'rgb(91, 235, 52)',
                            'borderWidth' => 2,
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                        ]
                    ],
                ],
            ]),
        ];
    }

    /**
     * Get system health metrics
     */
    public function getSystemHealth(): array
    {
        $memoryUsage = memory_get_usage(true);
        $memoryLimit = $this->parseBytes(ini_get('memory_limit'));
        $memoryPercent = $memoryLimit > 0 ? round(($memoryUsage / $memoryLimit) * 100, 1) : 0;

        $diskFree = disk_free_space('/');
        $diskTotal = disk_total_space('/');
        $diskUsed = $diskTotal - $diskFree;
        $diskPercent = $diskTotal > 0 ? round(($diskUsed / $diskTotal) * 100, 1) : 0;

        return [
            'php_version' => PHP_VERSION,
            'memory' => [
                'usage' => $this->formatBytes($memoryUsage),
                'limit' => ini_get('memory_limit'),
                'percent' => $memoryPercent,
                'status' => $memoryPercent < 70 ? 'ok' : ($memoryPercent < 90 ? 'warning' : 'error'),
            ],
            'disk' => [
                'free' => $this->formatBytes($diskFree),
                'total' => $this->formatBytes($diskTotal),
                'used' => $this->formatBytes($diskUsed),
                'percent' => $diskPercent,
                'status' => $diskPercent < 80 ? 'ok' : ($diskPercent < 95 ? 'warning' : 'error'),
            ],
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(): array
    {
        $tables = db()->fetchAll(
            "SELECT
                table_name,
                table_rows,
                ROUND(data_length / 1024 / 1024, 2) AS data_size_mb,
                ROUND(index_length / 1024 / 1024, 2) AS index_size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            ORDER BY table_rows DESC"
        );

        $totalRows = 0;
        $totalSize = 0;
        $tableCount = count($tables);

        foreach ($tables as $table) {
            $totalRows += (int)$table['table_rows'];
            $totalSize += (float)$table['data_size_mb'] + (float)$table['index_size_mb'];
        }

        return [
            'table_count' => $tableCount,
            'total_rows' => number_format($totalRows),
            'total_size' => number_format($totalSize, 2) . ' MB',
            'tables' => array_slice($tables, 0, 10),
        ];
    }

    /**
     * Get audit activity summary
     */
    public function getAuditSummary(): array
    {
        $todayCount = db()->execute(
            "SELECT COUNT(*) FROM audits WHERE DATE(created_at) = CURDATE()"
        )->fetchColumn();

        $byEvent = db()->fetchAll(
            "SELECT event, COUNT(*) as count
            FROM audits
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY event"
        );

        $byModel = db()->fetchAll(
            "SELECT auditable_type, COUNT(*) as count
            FROM audits
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY auditable_type
            ORDER BY count DESC
            LIMIT 5"
        );

        $eventCounts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
        foreach ($byEvent as $row) {
            $eventCounts[$row['event']] = (int)$row['count'];
        }

        $modelCounts = [];
        foreach ($byModel as $row) {
            $type = $row['auditable_type'];
            if (str_contains($type, '\\')) {
                $type = substr(strrchr($type, '\\'), 1);
            }
            $modelCounts[$type] = (int)$row['count'];
        }

        return [
            'today' => (int)$todayCount,
            'by_event' => $eventCounts,
            'by_model' => $modelCounts,
        ];
    }

    /**
     * Get user activity heatmap (7 days x 24 hours)
     */
    public function getUserActivityHeatmap(): array
    {
        $data = db()->fetchAll(
            "SELECT
                DAYOFWEEK(created_at) as dow,
                HOUR(created_at) as hour,
                COUNT(*) as count
            FROM sessions
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            GROUP BY dow, hour"
        );

        $matrix = [];
        $days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

        for ($d = 1; $d <= 7; $d++) {
            for ($h = 0; $h < 24; $h++) {
                $matrix[$d][$h] = 0;
            }
        }

        $maxCount = 1;
        foreach ($data as $row) {
            $dow = (int)$row['dow'];
            $hour = (int)$row['hour'];
            $count = (int)$row['count'];
            $matrix[$dow][$hour] = $count;
            if ($count > $maxCount) {
                $maxCount = $count;
            }
        }

        return [
            'matrix' => $matrix,
            'days' => $days,
            'hours' => range(0, 23),
            'max' => $maxCount,
        ];
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

    /**
     * Get system uptime if available
     */
    private function getUptime(): ?string
    {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $seconds = (int)explode(' ', $uptime)[0];

            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);

            return "{$days}d {$hours}h {$minutes}m";
        }
        return null;
    }
}
