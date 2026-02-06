<?php

/**
 * Redis Configuration
 *
 * Configure Redis connections for caching, sessions, rate limiting, and queues.
 * Each feature can use a separate database to keep data organized.
 */

return [
    'default' => [
        'host' => env('REDIS_HOST', 'redis'),
        'port' => (int) env('REDIS_PORT', 6379),
        'password' => env('REDIS_PASSWORD', null),
        'database' => (int) env('REDIS_DATABASE', 0),
        'prefix' => env('REDIS_PREFIX', 'echo:'),
    ],

    'cache' => [
        'database' => (int) env('REDIS_CACHE_DB', 1),
    ],

    'session' => [
        'database' => (int) env('REDIS_SESSION_DB', 2),
    ],

    'rate_limit' => [
        'database' => (int) env('REDIS_RATE_LIMIT_DB', 3),
    ],

    'queue' => [
        'database' => (int) env('REDIS_QUEUE_DB', 4),
    ],
];
