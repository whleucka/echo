<?php

namespace App\Http\Controllers;

use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

/**
 * Benchmark Controller
 *
 * Standard benchmark endpoints for measuring framework performance.
 * These follow TechEmpower Framework Benchmark conventions.
 *
 * Controlled by the BENCHMARKS_ENABLED env flag (defaults to false).
 * This is separate from APP_DEBUG so you can benchmark in production-like
 * configurations without exposing debug tooling.
 */
#[Group(pathPrefix: "/benchmark", namePrefix: "benchmark", middleware: ["benchmark"])]
class BenchmarkController extends Controller
{
    public function __construct()
    {
        if (!config('app.benchmarks')) {
            $this->pageNotFound();
        }
    }

    /**
     * Plaintext test - measures raw framework overhead
     * Returns "Hello, World!" as plain text
     */
    #[Get("/plaintext", "plaintext")]
    public function plaintext(): string
    {
        header('Content-Type: text/plain');
        return 'Hello, World!';
    }

    /**
     * JSON test - measures JSON serialization performance
     * Returns a simple JSON object
     */
    #[Get("/json", "json")]
    public function json(): string
    {
        header('Content-Type: application/json');
        return json_encode(['message' => 'Hello, World!']);
    }

    /**
     * Single query test - measures ORM/database overhead
     * Fetches a single row from the database
     */
    #[Get("/db", "db")]
    public function db(): string
    {
        header('Content-Type: application/json');

        // Use fixed ID for consistent performance measurement
        // (ORDER BY RAND() forces full table scan)
        $row = db()->fetch(
            "SELECT id, email, created_at FROM users WHERE id = 1 LIMIT 1"
        );

        return json_encode($row ?: ['error' => 'No data']);
    }

    /**
     * Multiple queries test - measures repeated database access
     * Fetches N rows (default 10, max 500)
     */
    #[Get("/queries/{count}", "queries")]
    public function queries(int $count = 10): string
    {
        header('Content-Type: application/json');

        $count = max(1, min(500, $count));
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $id = ($i % 100) + 1; // Cycle through IDs 1-100
            $results[] = db()->fetch(
                "SELECT id, email, created_at FROM users WHERE id = ? LIMIT 1",
                [$id]
            );
        }

        return json_encode($results);
    }

    /**
     * Template test - measures template rendering performance
     * Renders a simple Twig template
     */
    #[Get("/template", "template")]
    public function template(): string
    {
        return $this->render('benchmark/template.html.twig', [
            'message' => 'Hello, World!',
            'items' => range(1, 10),
        ]);
    }

    /**
     * Full stack test - database + template
     * Fetches data and renders it in a template
     */
    #[Get("/fullstack", "fullstack")]
    public function fullstack(): string
    {
        $users = db()->fetchAll(
            "SELECT id, email, first_name, surname, created_at
             FROM users
             ORDER BY created_at DESC
             LIMIT 10"
        );

        return $this->render('benchmark/fullstack.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Memory test - reports current memory usage
     */
    #[Get("/memory", "memory")]
    public function memory(): string
    {
        header('Content-Type: application/json');

        return json_encode([
            'memory_usage' => memory_get_usage(true),
            'memory_peak' => memory_get_peak_usage(true),
            'memory_usage_formatted' => format_bytes(memory_get_usage(true)),
            'memory_peak_formatted' => format_bytes(memory_get_peak_usage(true)),
        ]);
    }
}
