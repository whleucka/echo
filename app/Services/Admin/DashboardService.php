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
}
