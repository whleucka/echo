<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Audit\AuditLogger;
use Echo\Interface\Http\{Request, Middleware, Response};

/**
 * AuditContext Middleware
 *
 * Sets the audit context (user, IP, user agent) for audit logging.
 * This middleware should be registered after Auth middleware.
 */
class AuditContext implements Middleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = user();
        $userId = $user?->id ? (int) $user->id : null;

        $ipAddress = $request->getClientIp();
        $userAgent = $this->getUserAgent($request);

        AuditLogger::setContext($userId, $ipAddress, $userAgent);

        return $next($request);
    }

    /**
     * Get the user agent from the request
     */
    private function getUserAgent(Request $request): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}
