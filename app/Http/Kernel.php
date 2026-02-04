<?php

namespace App\Http;

use Echo\Framework\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected array $middleware_layers = [
        \Echo\Framework\Http\Middleware\RequestID::class,
        \Echo\Framework\Http\Middleware\Sessions::class,
        \Echo\Framework\Http\Middleware\CORS::class,          // CORS headers for API
        \Echo\Framework\Http\Middleware\ApiVersion::class,    // API versioning
        \Echo\Framework\Http\Middleware\Auth::class,
        \Echo\Framework\Http\Middleware\BearerAuth::class,    // Bearer token auth for API
        \Echo\Framework\Http\Middleware\Whitelist::class,
        \Echo\Framework\Http\Middleware\Blacklist::class,
        \Echo\Framework\Http\Middleware\RequestLimit::class,
        \Echo\Framework\Http\Middleware\CSRF::class,
    ];
}
