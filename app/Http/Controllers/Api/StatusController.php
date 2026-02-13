<?php

namespace App\Http\Controllers\Api;

use App\Services\Admin\SystemHealthService;
use Echo\Framework\Routing\Route\Get;

/**
 * API Status Controller
 *
 * Provides system status and health information via API.
 * Accessible at: api.example.com/v1/status
 */
class StatusController extends ApiController
{
    public function __construct(private SystemHealthService $service)
    {
    }

    /**
     * Get system status
     *
     * Returns basic system health information in JSON format.
     *
     * @return array System status data
     */
    #[Get('/status', 'status')]
    public function status(): array
    {
        $data = $this->service->getStatusJson();
        return $this->success($data);
    }
}
