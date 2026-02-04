<?php

namespace Echo\Framework\Debug;

/**
 * ProfilerStorage - Stores and retrieves profiler data from disk
 */
class ProfilerStorage
{
    private string $storagePath;
    private int $maxProfiles;
    private int $ttlSeconds;

    public function __construct(
        ?string $storagePath = null,
        int $maxProfiles = 50,
        int $ttlSeconds = 3600
    ) {
        $this->storagePath = $storagePath ?? config('paths.root') . 'storage/profiler/';
        $this->maxProfiles = $maxProfiles;
        $this->ttlSeconds = $ttlSeconds;

        $this->ensureDirectory();
    }

    /**
     * Store profiler data for a request
     */
    public function store(string $requestId, array $data): bool
    {
        $filename = $this->getFilePath($requestId);
        $data['_stored_at'] = time();

        $result = file_put_contents(
            $filename,
            json_encode($data, JSON_PRETTY_PRINT),
            LOCK_EX
        );

        // Cleanup old profiles periodically (1 in 10 chance)
        if (rand(1, 10) === 1) {
            $this->cleanup();
        }

        return $result !== false;
    }

    /**
     * Retrieve profiler data for a request
     */
    public function retrieve(string $requestId): ?array
    {
        $filename = $this->getFilePath($requestId);

        if (!file_exists($filename)) {
            return null;
        }

        $content = file_get_contents($filename);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null) {
            return null;
        }

        // Check TTL
        $storedAt = $data['_stored_at'] ?? 0;
        if (time() - $storedAt > $this->ttlSeconds) {
            unlink($filename);
            return null;
        }

        unset($data['_stored_at']);
        return $data;
    }

    /**
     * List all stored profile IDs
     */
    public function list(): array
    {
        $files = glob($this->storagePath . '*.json');
        $profiles = [];

        foreach ($files as $file) {
            $id = basename($file, '.json');
            $profiles[] = [
                'id' => $id,
                'time' => filemtime($file),
            ];
        }

        // Sort by time descending (newest first)
        usort($profiles, fn($a, $b) => $b['time'] <=> $a['time']);

        return $profiles;
    }

    /**
     * Cleanup old profiles
     */
    public function cleanup(): void
    {
        $files = glob($this->storagePath . '*.json');

        if (empty($files)) {
            return;
        }

        // Filter to only existing files and get their mtimes
        $fileData = [];
        foreach ($files as $file) {
            if (file_exists($file)) {
                $mtime = @filemtime($file);
                if ($mtime !== false) {
                    $fileData[] = ['path' => $file, 'mtime' => $mtime];
                }
            }
        }

        if (empty($fileData)) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($fileData, fn($a, $b) => $a['mtime'] <=> $b['mtime']);

        $now = time();
        $count = count($fileData);

        foreach ($fileData as $data) {
            $shouldDelete = false;

            // Delete if too old
            if ($now - $data['mtime'] > $this->ttlSeconds) {
                $shouldDelete = true;
            }
            // Delete if we have too many (keep newest)
            elseif ($count > $this->maxProfiles) {
                $shouldDelete = true;
                $count--;
            }

            if ($shouldDelete && file_exists($data['path'])) {
                @unlink($data['path']);
            }
        }
    }

    /**
     * Clear all stored profiles
     */
    public function clear(): int
    {
        $files = glob($this->storagePath . '*.json');
        $deleted = 0;

        foreach ($files as $file) {
            if (unlink($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * Get file path for a request ID
     */
    private function getFilePath(string $requestId): string
    {
        // Sanitize request ID to prevent directory traversal (allow alphanumeric and dash)
        $safeId = preg_replace('/[^a-zA-Z0-9-]/', '', $requestId);
        return $this->storagePath . $safeId . '.json';
    }

    /**
     * Ensure storage directory exists
     */
    private function ensureDirectory(): void
    {
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }
    }
}
