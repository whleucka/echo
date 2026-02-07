<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\Schema\TableSchemaBuilder;
use Echo\Framework\Admin\WidgetRegistry;
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/dashboard", name_prefix: "dashboard")]
class DashboardController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        // Dashboard uses custom rendering — no table
    }

    public function __construct(private DashboardService $service)
    {
        parent::__construct();
    }

    #[Get("/requests/chart/today", "requests.today.chart", ["max_requests" => 0])]
    public function requests_today_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getTodayRequestsChart());
    }

    #[Get("/requests/chart/week", "requests.week.chart", ["max_requests" => 0])]
    public function requests_week_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getWeekRequestsChart());
    }

    #[Get("/requests/chart/month", "requests.month.chart", ["max_requests" => 0])]
    public function requests_month_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getMonthRequestsChart());
    }

    #[Get("/requests/chart/ytd", "requests.ytd.chart", ["max_requests" => 0])]
    public function requests_ytd_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getYTDRequestsChart());
    }

    #[Get("/widgets/{id}", "widgets.render", ["max_requests" => 0])]
    public function render_widget(string $id): string
    {
        $widget = WidgetRegistry::get($id);

        if (!$widget) {
            return '<div class="alert alert-danger">Widget not found</div>';
        }

        return $widget->render();
    }

    #[Get("/widgets", "widgets.all", ["max_requests" => 0])]
    public function render_all_widgets(): string
    {
        return WidgetRegistry::renderAll();
    }

    protected function renderTable(): string
    {
        return $this->render("admin/dashboard.html.twig", [
            ...$this->getCommonData(),
        ]);
    }
}
