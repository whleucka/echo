<?php

return [
    // Allowed origins - use ['*'] to allow all, or specify domains
    'allowed_origins' => explode(',', env('CORS_ALLOWED_ORIGINS', '*')),

    // Allowed HTTP methods
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],

    // Allowed headers
    'allowed_headers' => [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'Accept',
        'Origin',
    ],

    // Headers exposed to the browser
    'exposed_headers' => [
        'X-Request-Id',
    ],

    // Allow credentials (cookies, authorization headers)
    'allow_credentials' => env('CORS_ALLOW_CREDENTIALS', false),

    // Preflight cache max age (seconds)
    'max_age' => 3600,
];
