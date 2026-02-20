<?php

namespace Echo\Framework\Debug;

/**
 * Request Profiler - Tracks request lifecycle metrics
 */
class RequestProfiler
{
    private float $startTime;
    private array $sections = [];
    private array $activeSections = [];

    public function __construct()
    {
        // Use REQUEST_TIME_FLOAT if available, otherwise current time
        $this->startTime = $_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true);
    }

    /**
     * Start timing a section
     */
    public function startSection(string $name): void
    {
        $this->activeSections[$name] = microtime(true);
    }

    /**
     * End timing a section
     */
    public function endSection(string $name): void
    {
        if (!isset($this->activeSections[$name])) {
            return;
        }

        $startTime = $this->activeSections[$name];
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        if (!isset($this->sections[$name])) {
            $this->sections[$name] = [
                'calls' => 0,
                'total_time' => 0,
                'instances' => [],
            ];
        }

        $this->sections[$name]['calls']++;
        $this->sections[$name]['total_time'] += $duration;
        $this->sections[$name]['instances'][] = [
            'start' => $startTime,
            'end' => $endTime,
            'duration' => $duration,
        ];

        unset($this->activeSections[$name]);
    }

    /**
     * Get the request start time
     */
    public function getStartTime(): float
    {
        return $this->startTime;
    }

    /**
     * Get total request time in milliseconds
     */
    public function getTotalTime(): float
    {
        return (microtime(true) - $this->startTime) * 1000;
    }

    /**
     * Get current memory usage in bytes
     */
    public function getMemoryUsage(): int
    {
        return memory_get_usage(true);
    }

    /**
     * Get peak memory usage in bytes
     */
    public function getPeakMemoryUsage(): int
    {
        return memory_get_peak_usage(true);
    }

    /**
     * Get all section timings
     */
    public function getSections(): array
    {
        return $this->sections;
    }

    /**
     * Get a summary of all metrics
     */
    public function getSummary(): array
    {
        return [
            'start_time' => $this->startTime,
            'total_time_ms' => round($this->getTotalTime(), 2),
            'memory_usage' => $this->getMemoryUsage(),
            'memory_usage_formatted' => format_bytes($this->getMemoryUsage()),
            'peak_memory' => $this->getPeakMemoryUsage(),
            'peak_memory_formatted' => format_bytes($this->getPeakMemoryUsage()),
            'sections' => $this->getSectionsSummary(),
        ];
    }

    /**
     * Get a summarized view of sections
     */
    private function getSectionsSummary(): array
    {
        $summary = [];
        foreach ($this->sections as $name => $data) {
            $summary[$name] = [
                'calls' => $data['calls'],
                'total_time_ms' => round($data['total_time'], 2),
                'avg_time_ms' => round($data['total_time'] / $data['calls'], 2),
            ];
        }
        return $summary;
    }

    /**
     * Get timeline data for visualization
     */
    public function getTimeline(): array
    {
        $timeline = [];
        $requestStart = $this->startTime;

        foreach ($this->sections as $name => $data) {
            foreach ($data['instances'] as $instance) {
                $timeline[] = [
                    'name' => $name,
                    'start_offset_ms' => round(($instance['start'] - $requestStart) * 1000, 2),
                    'duration_ms' => round($instance['duration'], 2),
                ];
            }
        }

        // Sort by start offset
        usort($timeline, fn($a, $b) => $a['start_offset_ms'] <=> $b['start_offset_ms']);

        return $timeline;
    }
}
