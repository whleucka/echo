<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\SystemHealthService;
use Echo\Framework\Admin\Schema\TableSchemaBuilder;
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/health", name_prefix: "health", middleware: ["max_requests" => 0])]
class HealthController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        // No table — health module uses custom rendering
    }

    public function __construct(private SystemHealthService $service)
    {
        parent::__construct();
    }

    /**
     * Display the health dashboard
     */
    protected function renderTable(): string
    {
        $checks = $this->service->runAllChecks();
        $overallStatus = $this->service->getOverallStatus();

        return $this->render("admin/health/index.html.twig", [
            ...$this->getCommonData(),
            'checks' => $checks,
            'overall_status' => $overallStatus,
        ]);
    }

    /**
     * Run a specific health check
     */
    #[Get("/check/{name}", "check")]
    public function check(string $name): string
    {
        $result = $this->service->getCheck($name);

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
     * Refresh all health checks
     */
    #[Get("/refresh", "refresh")]
    public function refresh(): string
    {
        $checks = $this->service->runAllChecks();
        $overallStatus = $this->service->getOverallStatus();

        return $this->render("admin/health/checks-list.html.twig", [
            'checks' => $checks,
            'overall_status' => $overallStatus,
        ]);
    }
}
