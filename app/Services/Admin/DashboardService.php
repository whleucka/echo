<?php

namespace App\Services\Admin;

use App\Models\Activity;
use App\Models\Audit;
use App\Models\EmailJob;
use App\Models\FileInfo;
use App\Models\Module;
use App\Models\User;

class DashboardService
{
    private \DateTimeZone $appTimezone;

    /**
     * Request-level cache for expensive queries called by multiple widgets
     */
    private array $cache = [];

    public function __construct(private SystemHealthService $healthService)
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
        return $this->cached('users_count', fn() => User::countAll());
    }

    public function getActiveUsersCount(): int
    {
        return $this->cached('active_users_count', function () {
            $now = $this->now()->format('Y-m-d H:i:s');
            $threshold = date('Y-m-d H:i:s', strtotime('-30 minutes', strtotime($now)));
            return Activity::where('created_at', '>=', $threshold)->count('DISTINCT user_id');
        });
    }

    public function getModulesCount(): int
    {
        return (new Module())->whereNotNull('parent_id')->count();
    }

    public function getTotalRequests(): int
    {
        return Activity::countAll();
    }

    public function getTodayRequests(): int
    {
        $now = $this->now();
        $todayStart = $now->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        $todayEnd = $now->setTime(23, 59, 59)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        return Activity::where('created_at', '>=', $todayStart)
            ->andWhere('created_at', '<=', $todayEnd)
            ->count();
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
        $memory = $this->healthService->getCheck('memory');
        $disk = $this->healthService->getCheck('disk');
        $phpVersion = $this->healthService->getCheck('php_version');

        return [
            'php_version' => $phpVersion['current'] ?? PHP_VERSION,
            'memory' => [
                'usage' => $memory['message'] ?? 'N/A',
                'limit' => ini_get('memory_limit'),
                'percent' => $memory['percent'] ?? 0,
                'status' => $memory['status'] ?? 'ok',
            ],
            'disk' => [
                'free' => $disk['free'] ?? 0,
                'total' => $disk['total'] ?? 0,
                'used' => $disk['used'] ?? 0,
                'percent' => $disk['percent'] ?? 0,
                'status' => $disk['status'] ?? 'ok',
            ],
            'uptime' => $this->getUptime(),
        ];
    }

    /**
     * Get database statistics
     */
    public function getDatabaseStats(): array
    {
        try {
            $tables = db()->fetchAll(
                "SELECT
                    table_name,
                    COALESCE(table_rows, 0) AS table_rows,
                    ROUND(COALESCE(data_length, 0) / 1024 / 1024, 2) AS data_size_mb,
                    ROUND(COALESCE(index_length, 0) / 1024 / 1024, 2) AS index_size_mb
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                    AND table_type = 'BASE TABLE'
                ORDER BY table_rows DESC"
            );
        } catch (\Throwable $e) {
            error_log('getDatabaseStats failed: ' . $e->getMessage());
            $tables = [];
        }

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

            // Use ORM for simple date-based count
            $todayCount = Audit::where('created_at', '>=', $today . ' 00:00:00')
                ->andWhere('created_at', '<=', $today . ' 23:59:59')
                ->count();

            // GROUP BY queries still use raw SQL (analytics-specific)
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

            // Use ORM for recent audits - models can access relations
            $recentAuditModels = Audit::where('id', '>', '0')
                ->orderBy('created_at', 'DESC')
                ->get(5);

            $recent = [];
            $audits = is_array($recentAuditModels) ? $recentAuditModels : ($recentAuditModels ? [$recentAuditModels] : []);
            foreach ($audits as $audit) {
                $user = $audit->user();
                $userName = $user ? trim($user->first_name . ' ' . $user->surname) : 'System';
                $recent[] = [
                    'id' => $audit->id,
                    'event' => $audit->event,
                    'type' => $audit->auditable_type,
                    'record_id' => $audit->auditable_id,
                    'user' => $userName,
                    'time_ago' => $this->timeAgo($audit->created_at),
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
        $newUsersWeek = User::where('created_at', '>=', $sevenDaysAgo)->count();

        // New users today
        $today = $this->now()->format('Y-m-d');
        $newUsersToday = User::where('created_at', '>=', $today . ' 00:00:00')
            ->andWhere('created_at', '<=', $today . ' 23:59:59')
            ->count();

        // Recent users (last 5 signups) - use ORM
        $recentUserModels = User::where('id', '>', '0')
            ->orderBy('created_at', 'DESC')
            ->get(5);

        $recent = [];
        $users = is_array($recentUserModels) ? $recentUserModels : ($recentUserModels ? [$recentUserModels] : []);
        foreach ($users as $user) {
            $recent[] = [
                'id' => $user->id,
                'name' => $user->fullName(),
                'email' => $user->email,
                'time_ago' => $this->timeAgo($user->created_at),
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
        $totalRequests = 0;
        foreach ($data as $row) {
            $dow = (int)$row['dow'];
            $hour = (int)$row['hour'];
            $count = (int)$row['count'];
            $matrix[$dow][$hour] = $count;
            $totalRequests += $count;
            if ($count > $maxCount) {
                $maxCount = $count;
            }
        }

        return [
            'matrix' => $matrix,
            'days' => $days,
            'hours' => range(0, 23),
            'max' => $maxCount,
            'total_requests' => $totalRequests,
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

        // Counts by status - GROUP BY still uses raw SQL (analytics-specific)
        $statusCounts = db()->fetchAll(
            "SELECT status, COUNT(*) as count FROM email_jobs GROUP BY status"
        );

        $statuses = ['pending' => 0, 'processing' => 0, 'sent' => 0, 'failed' => 0, 'exhausted' => 0];
        foreach ($statusCounts as $row) {
            $statuses[$row['status']] = (int)$row['count'];
        }

        // Sent today - use ORM
        $sentToday = EmailJob::where('status', 'sent')
            ->andWhere('sent_at', '>=', $today . ' 00:00:00')
            ->andWhere('sent_at', '<=', $today . ' 23:59:59')
            ->count();

        // Failed/exhausted in last 7 days - use ORM with whereRaw for IN clause
        $failedRecent = EmailJob::where('last_attempt_at', '>=', $sevenDaysAgo)
            ->whereRaw("status IN ('failed', 'exhausted')")
            ->count();

        // Recent jobs (last 5) - use ORM
        $recentJobModels = EmailJob::where('id', '>', '0')
            ->orderBy('created_at', 'DESC')
            ->get(5);

        $recent = [];
        $jobs = is_array($recentJobModels) ? $recentJobModels : ($recentJobModels ? [$recentJobModels] : []);
        foreach ($jobs as $job) {
            $recent[] = [
                'id' => $job->id,
                'to' => $job->to_address,
                'subject' => $job->subject,
                'status' => $job->status,
                'attempts' => (int)$job->attempts . '/' . (int)$job->max_attempts,
                'time_ago' => $this->timeAgo($job->created_at),
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
     * Get activity counts grouped by country code for a given time range
     */
    public function getCountryActivity(string $range = '7d'): array
    {
        $now = $this->now();

        $since = match ($range) {
            'today' => $now->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            '7d'    => $now->modify('-7 days')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            '30d'   => $now->modify('-30 days')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            'year'  => $now->modify('first day of January')->setTime(0, 0, 0)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            default => $now->modify('-7 days')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
        };

        $data = db()->fetchAll(
            "SELECT country_code, COUNT(*) as count
             FROM activity
             WHERE country_code IS NOT NULL
               AND created_at >= ?
             GROUP BY country_code
             ORDER BY count DESC",
            [$since]
        );

        $countries = [];
        $maxCount = 1;
        $totalRequests = 0;

        foreach ($data as $row) {
            $code = strtoupper($row['country_code']);
            $count = (int)$row['count'];
            $countries[$code] = $count;
            $totalRequests += $count;
            if ($count > $maxCount) {
                $maxCount = $count;
            }
        }

        return [
            'countries' => $countries,
            'max' => $maxCount,
            'total' => $totalRequests,
            'country_count' => count($countries),
            'range' => $range,
        ];
    }

    /**
     * Get system uptime if available
     */
    private function getUptime(): ?string
    {
        $uptime = $this->healthService->getCheck('uptime');
        return $uptime['uptime_formatted'] ?? null;
    }

    /**
     * Get file info statistics for dashboard widget
     */
    public function getFileInfoStats(): array
    {
        return $this->cached('file_info_stats', function () {
            $today = $this->now()->format('Y-m-d');

            // Today's uploads count
            $todayCount = FileInfo::where('created_at', '>=', $today . ' 00:00:00')
                ->andWhere('created_at', '<=', $today . ' 23:59:59')
                ->count();

            // Today's total size (raw SQL for SUM)
            $todaySize = db()->fetch(
                "SELECT COALESCE(SUM(size), 0) as total_size
                 FROM file_info
                 WHERE created_at >= ? AND created_at <= ?",
                [$today . ' 00:00:00', $today . ' 23:59:59']
            );

            // File type breakdown (images, documents, other)
            $typeBreakdown = db()->fetchAll(
                "SELECT
                    CASE
                        WHEN mime_type LIKE 'image/%' THEN 'images'
                        WHEN mime_type LIKE 'application/pdf'
                             OR mime_type LIKE 'application/msword%'
                             OR mime_type LIKE 'application/vnd.openxmlformats%'
                             OR mime_type LIKE 'text/%' THEN 'documents'
                        ELSE 'other'
                    END as file_type,
                    COUNT(*) as count
                 FROM file_info
                 WHERE created_at >= ? AND created_at <= ?
                 GROUP BY file_type",
                [$today . ' 00:00:00', $today . ' 23:59:59']
            );

            $types = ['images' => 0, 'documents' => 0, 'other' => 0];
            foreach ($typeBreakdown as $row) {
                $types[$row['file_type']] = (int)$row['count'];
            }

            // Recent uploads (last 5)
            $recentFiles = FileInfo::where('id', '>', '0')
                ->orderBy('created_at', 'DESC')
                ->get(5);

            $recent = [];
            $files = is_array($recentFiles) ? $recentFiles : ($recentFiles ? [$recentFiles] : []);
            foreach ($files as $file) {
                $recent[] = [
                    'id' => $file->id,
                    'name' => $file->original_name,
                    'size' => format_bytes((int)$file->size),
                    'mime_type' => $file->mime_type,
                    'time_ago' => $this->timeAgo($file->created_at),
                ];
            }

            return [
                'today_count' => (int)$todayCount,
                'today_size' => format_bytes((int)($todaySize['total_size'] ?? 0)),
                'by_type' => $types,
                'recent' => $recent,
            ];
        });
    }
}
