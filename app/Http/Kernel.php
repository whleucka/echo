<?php

namespace App\Http;

use Echo\Framework\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected array $middlewareLayers = [
        // 1. Assign request ID (needed by all downstream middleware)
        \Echo\Framework\Http\Middleware\RequestID::class,
        // 2–3. IP filtering first — reject before any session/DB work
        \Echo\Framework\Http\Middleware\Whitelist::class,
        \Echo\Framework\Http\Middleware\Blacklist::class,
        // 4. Rate limiting — before auth to limit brute-force overhead
        \Echo\Framework\Http\Middleware\RequestLimit::class,
        // 5. Activity logging
        \Echo\Framework\Http\Middleware\LogActivity::class,
        // 6–7. API negotiation
        \Echo\Framework\Http\Middleware\CORS::class,
        \Echo\Framework\Http\Middleware\ApiVersion::class,
        // 8–10. Authentication and context
        \Echo\Framework\Http\Middleware\Auth::class,
        \Echo\Framework\Http\Middleware\AuditContext::class,
        \Echo\Framework\Http\Middleware\BearerAuth::class,
        // 11. CSRF protection (requires session)
        \Echo\Framework\Http\Middleware\CSRF::class,
    ];
}
