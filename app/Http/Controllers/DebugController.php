<?php

namespace App\Http\Controllers;

use Echo\Framework\Debug\ProfilerStorage;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

/**
 * Debug Controller - Serves profiler data for the debug toolbar
 * Only active when APP_DEBUG=true
 */
#[Group(pathPrefix: "/_debug/profiler", namePrefix: "debug.profiler", middleware: ["debug"])]
class DebugController extends Controller
{
    #[Get("{id}", "profiler")]
    public function profiler(string $id): string
    {
        // Only allow in debug mode
        if (!config('app.debug')) {
            http_response_code(404);
            return $this->json(['error' => 'Not found']);
        }

        $storage = new ProfilerStorage();
        $data = $storage->retrieve($id);

        if ($data === null) {
            http_response_code(404);
            return $this->json(['error' => 'Profile not found or expired']);
        }

        return $this->json($data);
    }

    #[Get("/", "list")]
    public function list(): string
    {
        // Only allow in debug mode
        if (!config('app.debug')) {
            http_response_code(404);
            return $this->json(['error' => 'Not found']);
        }

        $storage = new ProfilerStorage();
        return $this->json($storage->list());
    }

    /**
     * Return JSON response with proper headers
     */
    private function json(array $data): string
    {
        $this->setHeader('Content-Type', 'application/json');
        return json_encode($data);
    }
}
