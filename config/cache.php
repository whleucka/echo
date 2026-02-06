<?php

/**
 * Cache Configuration
 *
 * Configure caching for the application.
 */

return [
    // Cache driver: "file" or "redis"
    'driver' => env('CACHE_DRIVER', 'file'),

    // Default TTL in seconds (1 hour)
    'ttl' => 3600,

    // File cache path (used when driver is "file")
    'path' => config('paths.root') . 'storage/cache',
];
