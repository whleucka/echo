<?php

namespace App\Http;

use Echo\Framework\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    protected array $middlewareLayers = [
        \Echo\Framework\Http\Middleware\RequestID::class,
        \Echo\Framework\Http\Middleware\LogActivity::class,
        \Echo\Framework\Http\Middleware\CORS::class,
        \Echo\Framework\Http\Middleware\ApiVersion::class,
        \Echo\Framework\Http\Middleware\Auth::class,
        \Echo\Framework\Http\Middleware\AuditContext::class,
        \Echo\Framework\Http\Middleware\BearerAuth::class,
        \Echo\Framework\Http\Middleware\Whitelist::class,
        \Echo\Framework\Http\Middleware\Blacklist::class,
        \Echo\Framework\Http\Middleware\RequestLimit::class,
        \Echo\Framework\Http\Middleware\CSRF::class,
    ];
}
