<?php

namespace Echo\Framework\Debug;

/**
 * Query Profiler - Tracks database query timing and statistics
 */
class QueryProfiler
{
    private array $queries = [];
    private float $totalTime = 0;

    /**
     * Log a query with timing information
     */
    public function log(string $sql, array $params, float $startTime): void
    {
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // Convert to milliseconds

        $this->queries[] = [
            'sql' => $sql,
            'params' => $params,
            'duration' => $duration,
            'start' => $startTime,
            'end' => $endTime,
            'backtrace' => $this->getBacktrace(),
        ];

        $this->totalTime += $duration;
    }

    /**
     * Get all logged queries
     */
    public function getQueries(): array
    {
        return $this->queries;
    }

    /**
     * Get total query count
     */
    public function getQueryCount(): int
    {
        return count($this->queries);
    }

    /**
     * Get total time spent on queries (in milliseconds)
     */
    public function getTotalTime(): float
    {
        return $this->totalTime;
    }

    /**
     * Get queries that exceed the threshold (in milliseconds)
     */
    public function getSlowQueries(?float $threshold = null): array
    {
        $threshold = $threshold ?? config('debug.slow_query_threshold') ?? 100;

        return array_filter($this->queries, fn($query) => $query['duration'] > $threshold);
    }

    /**
     * Get a simplified backtrace for debugging
     */
    private function getBacktrace(): array
    {
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 10);

        // Skip the profiler frames to get to the actual caller
        $relevant = [];
        $skipClasses = [
            'Echo\Framework\Debug\QueryProfiler',
            'Echo\Framework\Debug\Profiler',
            'Echo\Framework\Database\Connection',
            'Echo\Framework\Database\QueryBuilder',
        ];

        foreach ($trace as $frame) {
            $class = $frame['class'] ?? '';
            if (!in_array($class, $skipClasses)) {
                $relevant[] = [
                    'file' => $frame['file'] ?? 'unknown',
                    'line' => $frame['line'] ?? 0,
                    'class' => $class,
                    'function' => $frame['function'] ?? '',
                ];
                if (count($relevant) >= 3) {
                    break;
                }
            }
        }

        return $relevant;
    }

    /**
     * Clear all logged queries
     */
    public function clear(): void
    {
        $this->queries = [];
        $this->totalTime = 0;
    }

    /**
     * Get summary statistics
     */
    public function getSummary(): array
    {
        $count = $this->getQueryCount();
        $slowQueries = $this->getSlowQueries();

        return [
            'count' => $count,
            'total_time_ms' => round($this->totalTime, 2),
            'avg_time_ms' => $count > 0 ? round($this->totalTime / $count, 2) : 0,
            'slow_count' => count($slowQueries),
        ];
    }
}
