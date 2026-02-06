<?php

namespace Echo\Framework\Admin\Widgets;

use Echo\Framework\Admin\Widget;

class ActivityHeatmapWidget extends Widget
{
    protected string $id = 'activity-heatmap';
    protected string $title = 'Activity Heatmap (7 days)';
    protected string $icon = 'grid-3x3';
    protected string $template = 'admin/widgets/activity-heatmap.html.twig';
    protected int $width = 12;
    protected int $refreshInterval = 300; // 5 minutes
    protected int $cacheTtl = 60;
    protected int $priority = 10;

    public function getData(): array
    {
        $tz = config('app.timezone') ?? 'UTC';
        $appTimezone = new \DateTimeZone($tz);
        $now = new \DateTimeImmutable('now', $appTimezone);
        $tzOffset = $now->format('P');
        $weekAgo = $now->modify('-7 days')->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');

        $data = db()->fetchAll(
            "SELECT
                DAYOFWEEK(CONVERT_TZ(created_at, '+00:00', ?)) as dow,
                HOUR(CONVERT_TZ(created_at, '+00:00', ?)) as hour,
                COUNT(*) as count
            FROM sessions
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
}
