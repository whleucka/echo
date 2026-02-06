<?php

namespace Echo\Framework\Admin\Widgets;

use Echo\Framework\Admin\Widget;

class AuditSummaryWidget extends Widget
{
    protected string $id = 'audit-summary';
    protected string $title = 'Audit Activity (7 days)';
    protected string $icon = 'journal-text';
    protected string $template = 'admin/widgets/audit-summary.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 60;
    protected int $cacheTtl = 30;
    protected int $priority = 40;

    public function getData(): array
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
            $type = $audit['auditable_type'];
            if (str_contains($type, '\\')) {
                $type = substr(strrchr($type, '\\'), 1);
            }
            $recent[] = [
                'id' => $audit['id'],
                'event' => $audit['event'],
                'type' => $type,
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
    }

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
}
