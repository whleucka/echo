<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\NoTableTrait;
use Echo\Framework\Admin\WidgetRegistry;
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/dashboard", name_prefix: "dashboard", middleware: ["max_requests" => 0])]
class DashboardController extends ModuleController
{
    use NoTableTrait;
    protected string $table_name = "";

    public function __construct(private DashboardService $service)
    {
        parent::__construct();
    }

    #[Get("/requests/chart/today", "requests.today.chart")]
    public function requests_today_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getTodayRequestsChart());
    }

    #[Get("/requests/chart/week", "requests.week.chart")]
    public function requests_week_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getWeekRequestsChart());
    }

    #[Get("/requests/chart/month", "requests.month.chart")]
    public function requests_month_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getMonthRequestsChart());
    }

    #[Get("/requests/chart/ytd", "requests.ytd.chart")]
    public function requests_ytd_chart()
    {
        return $this->render('admin/dashboard-chart.html.twig', $this->service->getYTDRequestsChart());
    }

    #[Get("/widgets/{id}", "widgets.render")]
    public function render_widget(string $id): string
    {
        // Useful for rendering widget elsewhere
        $widget = WidgetRegistry::get($id);

        if (!$widget) {
            return '<div class="alert alert-danger">Widget not found</div>';
        }

        return $widget->render();
    }

    #[Get("/widgets", "widgets.all")]
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
