<?php

return [
    // Query profiler settings
    'slow_query_threshold' => env('DEBUG_SLOW_QUERY_MS', 100),

    // Debug toolbar settings
    'toolbar_enabled' => env('DEBUG_TOOLBAR', true),
    'toolbar_position' => 'bottom',

    // Logging settings
    'log_channel' => env('LOG_CHANNEL', 'app'),
    'log_json' => env('LOG_JSON', false),
    'log_level' => env('LOG_LEVEL', 'debug'),
];
