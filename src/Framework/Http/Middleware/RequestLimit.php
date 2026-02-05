<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\JsonResponse;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Interface\Http\{Request, Middleware, Response};

/**
 * Request limit
 */
class RequestLimit implements Middleware
{
    public function handle(Request $request, Closure $next): Response
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

        // Create a cache key
        $hash = md5($request->getClientIp());
        $key = "request_limit_" . $hash;

        // Set the session
        if (!session()->has($key)) {
            session()->set($key, [
                "count" => 0,
                "timestamp" => time(),
            ]);
        }

        $limit = session()->get($key);

        // Reset the count when expired
        if (time() - $limit["timestamp"] > $decay_seconds) {
            $limit["count"] = 0;
            $limit["timestamp"] = time();
        }

        // Increment request count
        $limit["count"]++;

        // Too many requests
        if ($limit["count"] > $max_requests) {
            $message = "Too many requests. Try again later.";
            return in_array("api", $middleware)
                ? new JsonResponse([
                    "id" => $request->getAttribute("request_id"),
                    "success" => false,
                    "status" => 429,
                    "error" => [
                        "code" => "RATE_LIMIT_EXCEEDED",
                        "message" => $message,
                    ],
                    "ts" => date(DATE_ATOM)], 429)
                : new HttpResponse($message, 429);
        }

        // Set the request session
        session()->set($key, $limit);

        return $next($request);
    }
}
