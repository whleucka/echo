<?php

namespace App\Services\Admin;

class DashboardService
{
    private \DateTimeZone $appTimezone;

    /**
     * Request-level cache for expensive queries called by multiple widgets
     */
    private array $cache = [];

    public function __construct()
    {
        $tz = config('app.timezone') ?? 'UTC';
        $this->appTimezone = new \DateTimeZone($tz);
    }

    /**
     * Get a cached value or compute and cache it
     */
    private function cached(string $key, callable $callback): mixed
    {
        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $callback();
        }
        return $this->cache[$key];
    }

    /**
     * Get current date/time in app timezone
     */
    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', $this->appTimezone);
    }

    public function getUsersCount(): int
    {
        return $this->cached('users_count', fn() => db()->execute(
            "SELECT count(*) FROM users"
        )->fetchColumn());
    }

    public function getActiveUsersCount(): int
    {
        return $this->cached('active_users_count', function () {
            $now = $this->now()->format('Y-m-d H:i:s');
            $threshold = date('Y-m-d H:i:s', strtotime('-30 minutes', strtotime($now)));
            return db()->execute(
                "SELECT COUNT(DISTINCT user_id) AS active_users
                FROM activity
                WHERE created_at >= ?",
                [$threshold]
            )->fetchColumn();
        });
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
            "SELECT count(*) FROM activity"
        )->fetchColumn();
    }

    public function getTodayRequests(): int
    {
        $now = $this->now();
        $todayStart = $now->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $todayEnd = $now->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        return db()->execute(
            "SELECT count(*) FROM activity WHERE created_at BETWEEN ? AND ?",
            [$todayStart, $todayEnd]
        )->fetchColumn();
    }

    public function getTodayRequestsChart(): array
    {
        $now = $this->now();
        $todayStart = $now->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $todayEnd = $now->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $tzOffset = $now->format('P'); // e.g., "+05:00" or "-08:00"

        $data = db()->fetchAll(
            "SELECT
                HOUR(CONVERT_TZ(created_at, '+00:00', ?)) AS hour,
                COUNT(*) AS total
            FROM activity
            WHERE created_at BETWEEN ? AND ?
            GROUP BY hour
            ORDER BY hour",
            [$tzOffset, $todayStart, $todayEnd]
        );

        $hours = range(0, 23);
        $payload = array_fill(0, 24, 0);

        foreach ($data as $row) {
            $payload[(int)$row['hour']] = (int)$row['total'];
        }

        $labels = array_map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . ":00", $hours);
        $today = $now->format("l, F d, Y");

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
                            'fill' => true,
                            'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                            'borderColor' => '#3b82f6',
                            'borderWidth' => 2,
                            'tension' => 0.3,
                            'pointBackgroundColor' => '#3b82f6',
                            'pointBorderColor' => '#fff',
                            'pointBorderWidth' => 2,
                            'pointRadius' => 4,
                            'pointHoverRadius' => 6,
                            'pointHoverBackgroundColor' => '#fff',
                            'pointHoverBorderColor' => '#3b82f6',
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => (object)[
                        'legend' => (object)[
                            'display' => false,
                        ],
                    ],
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                            'grid' => (object)[
                                'color' => 'rgba(0, 0, 0, 0.05)',
                            ],
                        ],
                        'x' => (object)[
                            'grid' => (object)[
                                'display' => false,
                            ],
                        ],
                    ],
                ],
            ]),
        ];
    }

    public function getWeekRequestsChart(): array
    {
        $now = $this->now();
        $tzOffset = $now->format('P');

        // Compute ISO week boundaries (Mon-Sun) in UTC so the index is used
        $dayOfWeek = (int)$now->format('N'); // 1=Mon, 7=Sun
        $weekStart = $now->modify('-' . ($dayOfWeek - 1) . ' days')->setTime(0, 0, 0)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $weekEnd = $now->modify('+' . (7 - $dayOfWeek) . ' days')->setTime(23, 59, 59)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $data = db()->fetchAll(
            "SELECT
                MIN(DAYNAME(CONVERT_TZ(created_at, '+00:00', ?))) AS day_name,
                DATE(CONVERT_TZ(created_at, '+00:00', ?)) AS day_date,
                COUNT(*) AS total
            FROM activity
            WHERE created_at BETWEEN ? AND ?
            GROUP BY day_date
            ORDER BY day_date",
            [$tzOffset, $tzOffset, $weekStart, $weekEnd]
        );

        $labels = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
        $payload = array_fill(0, 7, 0);

        foreach ($data as $row) {
            $index = array_search($row['day_name'], $labels);
            if ($index !== false) {
                $payload[$index] = (int)$row['total'];
            }
        }

        $week = $now->format("W");

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
                            'backgroundColor' => 'rgba(245, 158, 11, 0.8)',
                            'borderColor' => '#f59e0b',
                            'borderWidth' => 0,
                            'borderRadius' => 6,
                            'hoverBackgroundColor' => '#d97706',
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => (object)[
                        'legend' => (object)[
                            'display' => false,
                        ],
                    ],
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                            'grid' => (object)[
                                'color' => 'rgba(0, 0, 0, 0.05)',
                            ],
                        ],
                        'x' => (object)[
                            'grid' => (object)[
                                'display' => false,
                            ],
                        ],
                    ],
                ],
            ]),
        ];
    }

    public function getMonthRequestsChart(): array
    {
        $now = $this->now();
        $tzOffset = $now->format('P');

        // Compute month boundaries in UTC so the index on created_at is used
        $monthStart = $now->modify('first day of this month')->setTime(0, 0, 0)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $monthEnd = $now->modify('last day of this month')->setTime(23, 59, 59)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $data = db()->fetchAll(
            "SELECT
                DAY(CONVERT_TZ(created_at, '+00:00', ?)) AS day_number,
                COUNT(*) AS total
            FROM activity
            WHERE created_at BETWEEN ? AND ?
            GROUP BY day_number
            ORDER BY day_number",
            [$tzOffset, $monthStart, $monthEnd]
        );

        $daysInMonth = (int)$now->format('t');
        $labels = range(1, $daysInMonth);
        $payload = array_fill(0, $daysInMonth, 0);

        foreach ($data as $row) {
            $payload[$row['day_number'] - 1] = (int)$row['total'];
        }

        $month = $now->format('F, Y');

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
                            'backgroundColor' => 'rgba(139, 92, 246, 0.8)',
                            'borderColor' => '#8b5cf6',
                            'borderWidth' => 0,
                            'borderRadius' => 6,
                            'hoverBackgroundColor' => '#7c3aed',
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => (object)[
                        'legend' => (object)[
                            'display' => false,
                        ],
                    ],
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                            'grid' => (object)[
                                'color' => 'rgba(0, 0, 0, 0.05)',
                            ],
                        ],
                        'x' => (object)[
                            'grid' => (object)[
                                'display' => false,
                            ],
                        ],
                    ],
                ],
            ]),
        ];
    }

    public function getYTDRequestsChart(): array
    {
        $now = $this->now();
        $tzOffset = $now->format('P');

        // Compute Jan 1 in local TZ, convert to UTC so the index is used
        $yearStart = $now->modify('first day of January')->setTime(0, 0, 0)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $yearEnd = $now->setTime(23, 59, 59)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $data = db()->fetchAll(
            "SELECT DATE_FORMAT(CONVERT_TZ(created_at, '+00:00', ?), '%Y-%m') AS month, COUNT(*) AS total
            FROM activity
            WHERE created_at BETWEEN ? AND ?
            GROUP BY month
            ORDER BY month",
            [$tzOffset, $yearStart, $yearEnd]
        );

        $labels = [];
        $payload = [];

        foreach ($data as $row) {
            $dt = \DateTimeImmutable::createFromFormat('Y-m', $row['month'], $this->appTimezone);
            $labels[] = $dt->format('M Y');
            $payload[] = (int)$row['total'];
        }

        $year = $now->format("Y");

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
                            'backgroundColor' => 'rgba(34, 197, 94, 0.8)',
                            'borderColor' => '#22c55e',
                            'borderWidth' => 0,
                            'borderRadius' => 6,
                            'hoverBackgroundColor' => '#16a34a',
                        ]
                    ]
                ],
                'options' => (object)[
                    'responsive' => true,
                    'maintainAspectRatio' => false,
                    'plugins' => (object)[
                        'legend' => (object)[
                            'display' => false,
                        ],
                    ],
                    'scales' => (object)[
                        'y' => (object)[
                            'beginAtZero' => true,
                            'grid' => (object)[
                                'color' => 'rgba(0, 0, 0, 0.05)',
                            ],
                        ],
                        'x' => (object)[
                            'grid' => (object)[
                                'display' => false,
                            ],
                        ],
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
        return $this->cached('audit_summary', function () {
            $today = $this->now()->format('Y-m-d');
            $sevenDaysAgo = $this->now()->modify('-7 days')->format('Y-m-d H:i:s');

            $todayCount = db()->execute(
                "SELECT COUNT(*) FROM audits WHERE DATE(created_at) = ?",
                [$today]
            )->fetchColumn();

            $byEvent = db()->fetchAll(
                "SELECT event, COUNT(*) as count
                FROM audits
                WHERE created_at >= ?
                GROUP BY event",
                [$sevenDaysAgo]
            );

            $byModel = db()->fetchAll(
                "SELECT auditable_type, COUNT(*) as count
                FROM audits
                WHERE created_at >= ?
                GROUP BY auditable_type
                ORDER BY count DESC
                LIMIT 5",
                [$sevenDaysAgo]
            );

            $eventCounts = ['created' => 0, 'updated' => 0, 'deleted' => 0];
            foreach ($byEvent as $row) {
                $eventCounts[$row['event']] = (int)$row['count'];
            }

            $modelCounts = [];
            foreach ($byModel as $row) {
                $modelCounts[$row['auditable_type']] = (int)$row['count'];
            }

            // Get recent activity (last 5)
            $recentAudits = db()->fetchAll(
                "SELECT
                    a.id,
                    a.event,
                    a.auditable_type,
                    a.auditable_id,
                    a.created_at,
                    COALESCE(CONCAT(u.first_name, ' ', u.surname), 'System') as user_name
                FROM audits a
                LEFT JOIN users u ON u.id = a.user_id
                ORDER BY a.created_at DESC
                LIMIT 5"
            );

            $recent = [];
            foreach ($recentAudits as $audit) {
                $recent[] = [
                    'id' => $audit['id'],
                    'event' => $audit['event'],
                    'type' => $audit['auditable_type'],
                    'record_id' => $audit['auditable_id'],
                    'user' => $audit['user_name'],
                    'time_ago' => $this->timeAgo($audit['created_at']),
                ];
            }

            return [
                'today' => (int)$todayCount,
                'by_event' => $eventCounts,
                'by_model' => $modelCounts,
                'recent' => $recent,
            ];
        });
    }

    /**
     * Format time ago string
     */
    private function timeAgo(string $datetime): string
    {
        $time = strtotime($datetime);
        $diff = time() - $time;

        if ($diff < 60) {
            return 'just now';
        }

        $intervals = [
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
        ];

        foreach ($intervals as $seconds => $label) {
            $count = floor($diff / $seconds);
            if ($count >= 1) {
                return $count . ' ' . $label . ($count > 1 ? 's' : '') . ' ago';
            }
        }

        return 'just now';
    }

    /**
     * Get users widget data (totals, recent signups, active now)
     */
    public function getUsersWidgetData(): array
    {
        $totalUsers = $this->getUsersCount();
        $activeUsers = $this->getActiveUsersCount();

        // New users in the last 7 days
        $sevenDaysAgo = $this->now()->modify('-7 days')->format('Y-m-d H:i:s');
        $newUsersWeek = db()->execute(
            "SELECT COUNT(*) FROM users WHERE created_at >= ?",
            [$sevenDaysAgo]
        )->fetchColumn();

        // New users today
        $today = $this->now()->format('Y-m-d');
        $newUsersToday = db()->execute(
            "SELECT COUNT(*) FROM users WHERE DATE(created_at) = ?",
            [$today]
        )->fetchColumn();

        // Recent users (last 5 signups)
        $recentUsers = db()->fetchAll(
            "SELECT id, first_name, surname, email, created_at
            FROM users
            ORDER BY created_at DESC
            LIMIT 5"
        );

        $recent = [];
        foreach ($recentUsers as $user) {
            $recent[] = [
                'id' => $user['id'],
                'name' => trim(($user['first_name'] ?? '') . ' ' . ($user['surname'] ?? '')),
                'email' => $user['email'],
                'time_ago' => $this->timeAgo($user['created_at']),
            ];
        }

        return [
            'total' => (int)$totalUsers,
            'active' => (int)$activeUsers,
            'new_week' => (int)$newUsersWeek,
            'new_today' => (int)$newUsersToday,
            'recent' => $recent,
        ];
    }

    /**
     * Get user activity heatmap (7 days x 24 hours)
     */
    public function getUserActivityHeatmap(): array
    {
        $now = $this->now();
        $tzOffset = $now->format('P');
        $weekAgo = $now->modify('-7 days')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $data = db()->fetchAll(
            "SELECT
                DAYOFWEEK(CONVERT_TZ(created_at, '+00:00', ?)) as dow,
                HOUR(CONVERT_TZ(created_at, '+00:00', ?)) as hour,
                COUNT(*) as count
            FROM activity
            WHERE created_at >= ?
            GROUP BY dow, hour",
            [$tzOffset, $tzOffset, $weekAgo]
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
     * Get email queue widget data
     */
    public function getEmailQueueData(): array
    {
        $now = $this->now();
        $today = $now->format('Y-m-d');
        $sevenDaysAgo = $now->modify('-7 days')->format('Y-m-d H:i:s');

        // Counts by status
        $statusCounts = db()->fetchAll(
            "SELECT status, COUNT(*) as count FROM email_jobs GROUP BY status"
        );

        $statuses = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'exhausted' => 0];
        foreach ($statusCounts as $row) {
            $statuses[$row['status']] = (int)$row['count'];
        }

        // Sent today
        $sentToday = db()->execute(
            "SELECT COUNT(*) FROM email_jobs WHERE status = 'sent' AND DATE(sent_at) = ?",
            [$today]
        )->fetchColumn();

        // Failed/exhausted in last 7 days
        $failedRecent = db()->execute(
            "SELECT COUNT(*) FROM email_jobs WHERE status IN ('failed', 'exhausted') AND last_attempt_at >= ?",
            [$sevenDaysAgo]
        )->fetchColumn();

        // Recent jobs (last 5)
        $recentJobs = db()->fetchAll(
            "SELECT id, to_address, subject, status, attempts, max_attempts, created_at, sent_at, last_attempt_at
            FROM email_jobs
            ORDER BY created_at DESC
            LIMIT 5"
        );

        $recent = [];
        foreach ($recentJobs as $job) {
            $recent[] = [
                'id' => $job['id'],
                'to' => $job['to_address'],
                'subject' => $job['subject'],
                'status' => $job['status'],
                'attempts' => (int)$job['attempts'] . '/' . (int)$job['max_attempts'],
                'time_ago' => $this->timeAgo($job['created_at']),
            ];
        }

        return [
            'statuses' => $statuses,
            'total' => array_sum($statuses),
            'sent_today' => (int)$sentToday,
            'failed_recent' => (int)$failedRecent,
            'recent' => $recent,
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
