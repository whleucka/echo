<?php

namespace Echo\Framework\Admin\Widgets;

use Echo\Framework\Admin\Widget;

class ActivityFeedWidget extends Widget
{
    protected string $id = 'activity-feed';
    protected string $title = 'Recent Activity';
    protected string $icon = 'activity';
    protected string $template = 'admin/widgets/activity-feed.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 30;

    public function getData(): array
    {
        $audits = db()->fetchAll(
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
            LIMIT 10"
        );

        $activities = [];
        foreach ($audits as $audit) {
            $type = $audit['auditable_type'];
            if (str_contains($type, '\\')) {
                $type = substr(strrchr($type, '\\'), 1);
            }

            $activities[] = [
                'id' => $audit['id'],
                'event' => $audit['event'],
                'type' => $type,
                'record_id' => $audit['auditable_id'],
                'user' => $audit['user_name'],
                'time' => $audit['created_at'],
                'time_ago' => $this->timeAgo($audit['created_at']),
            ];
        }

        return [
            'activities' => $activities,
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
