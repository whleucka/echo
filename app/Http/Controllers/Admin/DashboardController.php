<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\DashboardService;
use Echo\Framework\Admin\WidgetRegistry;
use Echo\Framework\Http\AdminController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/dashboard", name_prefix: "dashboard")]
class DashboardController extends AdminController
{
    public function __construct(private DashboardService $service) 
    {
        parent::__construct();
    }

    #[Get("/sales/total", "sales.total")]
    public function sales(): string
    {
        return $this->service->getTotalSales();
    }

    #[Get("/sales/today", "sales.today")]
    public function sales_today(): string
    {
        return $this->service->getTodaySales();
    }

    #[Get("/users/count", "users.count")]
    public function users_count(): int
    {
        return $this->service->getUsersCount();
    }

    #[Get("/users/active", "users.active")]
    public function users_active(): int
    {
        return $this->service->getActiveUsersCount();
    }


    #[Get("/customers/count", "customers.count")]
    public function customers_count(): int
    {
        return $this->service->getCustomersCount();
    }

    #[Get("/customers/new", "customers.new")]
    public function customers_new(): int
    {
        return $this->service->getNewCustomersCount();
    }

    #[Get("/modules/count", "modules.count")]
    public function modules_count(): int
    {
        return $this->service->getModulesCount();
    }

    #[Get("/requests/count/total", "requests.total")]
    public function requests_total(): int
    {
        return $this->service->getTotalRequests();
    }

    #[Get("/requests/count/today", "requests.today")]
    public function requests_today(): int
    {
        return $this->service->getTodayRequests();
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
