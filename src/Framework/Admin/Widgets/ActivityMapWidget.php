<?php

namespace Echo\Framework\Admin\Widgets;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Widget;

class ActivityMapWidget extends Widget
{
    protected string $id = 'activity-map';
    protected string $title = 'Activity by Country';
    protected string $icon = 'globe-americas';
    protected string $template = 'admin/widgets/activity-map.html.twig';
    protected int $width = 12;
    protected int $refreshInterval = 300; // 5 minutes
    protected int $cacheTtl = 0; // no cache -- range is dynamic
    protected int $priority = 25; // before the heatmap

    public function __construct(private DashboardService $dashboardService)
    {
    }

    public function getData(): array
    {
        $range = request()->get->get('range') ?? 'today';
        $validRanges = ['today', '7d', '30d', 'year'];
        if (!in_array($range, $validRanges, true)) {
            $range = 'today';
        }

        $data = $this->dashboardService->getCountryActivity($range);

        // Build top countries list with flags for the legend
        $top = [];
        $i = 0;
        foreach ($data['countries'] as $code => $count) {
            if ($i >= 10) break;
            $top[] = [
                'code' => $code,
                'flag' => $this->countryFlag($code),
                'count' => number_format($count),
            ];
            $i++;
        }
        $data['top'] = $top;

        // JSON-encode countries for the JS map
        $data['countries_json'] = json_encode($data['countries']);

        return $data;
    }

    /**
     * Convert a 2-letter country code to a flag icon
     */
    private function countryFlag(string $code): string
    {
        $code = strtolower($code);
        if (strlen($code) !== 2) {
            return '';
        }
        return sprintf('<span class="fi fi-%s"></span>', $code);
    }
}
