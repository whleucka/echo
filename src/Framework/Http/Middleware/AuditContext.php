<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Audit\AuditLogger;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * AuditContext Middleware
 *
 * Sets the audit context (user, IP, user agent) for audit logging.
 * This middleware should be registered after Auth middleware.
 */
class AuditContext implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
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
    private function getUserAgent(RequestInterface $request): ?string
    {
        return $_SERVER['HTTP_USER_AGENT'] ?? null;
    }
}
