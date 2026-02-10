<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\JsonResponse;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;
use Echo\Framework\RateLimit\RateLimiter;
use Echo\Framework\RateLimit\RedisRateLimiter;
use Echo\Framework\RateLimit\SessionRateLimiter;

/**
 * Request Limit Middleware
 *
 * Rate limits requests using Redis (preferred) or sessions (fallback).
 * Supports per-route rate limiting via middleware parameters.
 */
class RequestLimit implements MiddlewareInterface
{
    private ?RateLimiter $limiter = null;

    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"];

        // Maybe it is disabled?
        if (isset($middleware["max_requests"]) && $middleware["max_requests"] == 0) {
            return $next($request);
        }

        // Check if this is an HTMX request
        $isHtmx = $request->isHTMX();

        // Get the max requests and decay seconds
        if (in_array("api", $middleware)) {
            $max_requests = 60;
            $decay_seconds = 60;
        } elseif ($isHtmx) {
            // Higher limit for HTMX requests (authenticated user interactions)
            $max_requests = $middleware["max_requests"] ?? config("security.max_requests_htmx") ?? 1000;
            $decay_seconds = $middleware["decay_seconds"] ?? config("security.decay_seconds");
        } else {
            $max_requests = $middleware["max_requests"] ?? config("security.max_requests");
            $decay_seconds = $middleware["decay_seconds"] ?? config("security.decay_seconds");
        }

        // Get rate limiter
        $limiter = $this->getLimiter();

        // Build rate limit key: per-route when max_requests is explicitly set,
        // otherwise global per-IP. This gives auth endpoints their own bucket.
        $ip = $request->getClientIp() ?? 'unknown';
        if (isset($middleware["max_requests"])) {
            $routePath = $route["path"] ?? '';
            $key = "request_limit:" . md5($ip . ':' . $routePath);
        } else {
            $key = "request_limit:" . md5($ip);
        }

        // Attempt the request
        if (!$limiter->attempt($key, $max_requests, $decay_seconds)) {
            $retryAfter = $limiter->retryAfter($key);
            $message = "Too many requests. Try again in {$retryAfter} seconds.";

            // Log rate limit hits
            logger()->channel('auth')->warning('Rate limit exceeded', [
                'ip' => $ip,
                'path' => $route["path"] ?? '',
                'max_requests' => $max_requests,
                'retry_after' => $retryAfter,
            ]);

            return in_array("api", $middleware)
                ? new JsonResponse([
                    "id" => $request->getAttribute("request_id"),
                    "success" => false,
                    "status" => 429,
                    "error" => [
                        "code" => "RATE_LIMIT_EXCEEDED",
                        "message" => $message,
                        "retry_after" => $retryAfter,
                    ],
                    "ts" => date(DATE_ATOM)], 429)
                : new HttpResponse($message, 429);
        }

        return $next($request);
    }

    /**
     * Get the rate limiter instance
     */
    private function getLimiter(): RateLimiter
    {
        if ($this->limiter === null) {
            // Try Redis first, fall back to session
            try {
                if (redis()->isAvailable()) {
                    $this->limiter = new RedisRateLimiter();
                } else {
                    $this->limiter = new SessionRateLimiter();
                }
            } catch (\Throwable) {
                $this->limiter = new SessionRateLimiter();
            }
        }
        return $this->limiter;
    }
}
