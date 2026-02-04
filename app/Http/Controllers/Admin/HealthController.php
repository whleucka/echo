<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\SystemHealthService;
use Echo\Framework\Http\AdminController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/health", name_prefix: "health")]
class HealthController extends AdminController
{
    public function __construct(private SystemHealthService $healthService)
    {
        parent::__construct();
    }

    /**
     * Display the health dashboard
     */
    protected function renderTable(): string
    {
        $checks = $this->healthService->runAllChecks();
        $overallStatus = $this->healthService->getOverallStatus();

        return $this->render("admin/health/index.html.twig", [
            ...$this->getCommonData(),
            'checks' => $checks,
            'overall_status' => $overallStatus,
        ]);
    }

    /**
     * Run a specific health check
     */
    #[Get("/check/{name}", "check", ["max_requests" => 0])]
    public function check(string $name): string
    {
        $result = $this->healthService->getCheck($name);

        if ($result === null) {
            return $this->render("admin/health/check.html.twig", [
                'name' => $name,
                'check' => [
                    'status' => 'error',
                    'message' => 'Check not found',
                ],
            ]);
        }

        return $this->render("admin/health/check.html.twig", [
            'name' => $name,
            'check' => $result,
        ]);
    }

    /**
     * Get health status as JSON (for monitoring tools)
     */
    #[Get("/api", "api", ["max_requests" => 0])]
    public function api(): string
    {
        $data = $this->healthService->getStatusJson();

        header('Content-Type: application/json');
        return json_encode($data, JSON_PRETTY_PRINT);
    }

    /**
     * Refresh all health checks
     */
    #[Get("/refresh", "refresh", ["max_requests" => 0])]
    public function refresh(): string
    {
        $checks = $this->healthService->runAllChecks();
        $overallStatus = $this->healthService->getOverallStatus();

        return $this->render("admin/health/checks-list.html.twig", [
            'checks' => $checks,
            'overall_status' => $overallStatus,
        ]);
    }
}
