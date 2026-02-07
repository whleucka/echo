<?php

namespace App\Http\Controllers;

use App\Services\Admin\SystemHealthService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Route\Get;

/**
 * Status Controller
 */
class StatusController extends Controller
{
    public function __construct(private SystemHealthService $service)
    {
    }

    #[Get("/api/status", "status.api", ["api"])]
    public function status(): ?array
    {
        $data = $this->service->getStatusJson();
        return $data;
    }
}
