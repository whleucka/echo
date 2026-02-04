<?php

namespace Echo\Framework\Debug;

use Echo\Traits\Creational\Singleton;

/**
 * Central Profiler - Orchestrates all profiling subsystems
 */
class Profiler
{
    use Singleton;

    private ?QueryProfiler $queryProfiler = null;
    private ?RequestProfiler $requestProfiler = null;
    private bool $enabled;

    public function __construct()
    {
        $this->enabled = (bool) config('app.debug');

        if ($this->enabled) {
            $this->queryProfiler = new QueryProfiler();
            $this->requestProfiler = new RequestProfiler();
        }
    }

    /**
     * Get singleton instance
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if profiler is enabled
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Get query profiler instance
     */
    public function queries(): ?QueryProfiler
    {
        return $this->queryProfiler;
    }

    /**
     * Get request profiler instance
     */
    public function request(): ?RequestProfiler
    {
        return $this->requestProfiler;
    }

    /**
     * Get a complete summary of all profiling data
     */
    public function getSummary(): array
    {
        if (!$this->enabled) {
            return ['enabled' => false];
        }

        return [
            'enabled' => true,
            'request' => $this->requestProfiler?->getSummary() ?? [],
            'queries' => [
                'summary' => $this->queryProfiler?->getSummary() ?? [],
                'list' => $this->queryProfiler?->getQueries() ?? [],
                'slow' => $this->queryProfiler?->getSlowQueries() ?? [],
            ],
            'timeline' => $this->requestProfiler?->getTimeline() ?? [],
        ];
    }

    /**
     * Start timing a section
     */
    public function startSection(string $name): void
    {
        $this->requestProfiler?->startSection($name);
    }

    /**
     * End timing a section
     */
    public function endSection(string $name): void
    {
        $this->requestProfiler?->endSection($name);
    }
}
