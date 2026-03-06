<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Framework\Http\Response as HttpResponse;
use Echo\Framework\Http\RequestInterface;
use Echo\Framework\Http\ResponseInterface;
use Echo\Framework\Http\MiddlewareInterface;

/**
 * CORS Middleware
 * Handles Cross-Origin Resource Sharing headers
 */
class CORS implements MiddlewareInterface
{
    public function handle(RequestInterface $request, Closure $next): ResponseInterface
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"] ?? [];

        // Only apply CORS to API routes
        if (!in_array('api', $middleware)) {
            return $next($request);
        }

        // Read request origin from headers (avoid direct $_SERVER access)
        $origin = $request->headers->get('Origin') ?: null;

        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->preflightResponse($origin);
        }

        // Process request and add CORS headers to response
        $response = $next($request);
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Handle preflight OPTIONS request
     */
    private function preflightResponse(?string $origin): ResponseInterface
    {
        $response = new HttpResponse('', 204);
        return $this->addCorsHeaders($response, $origin);
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(ResponseInterface $response, ?string $origin): ResponseInterface
    {
        $config = config('cors');
        $allowCredentials = $config['allow_credentials'] ?? false;

        // Allowed origins
        $origins = $config['allowed_origins'] ?? ['*'];

        if (in_array('*', $origins)) {
            if ($allowCredentials && $origin) {
                // Wildcard + credentials is invalid per CORS spec.
                // Reflect the specific request origin instead.
                $response->setHeader('Access-Control-Allow-Origin', $origin);
                $response->setHeader('Vary', 'Origin');
            } else {
                $response->setHeader('Access-Control-Allow-Origin', '*');
            }
        } elseif ($origin && in_array($origin, $origins)) {
            $response->setHeader('Access-Control-Allow-Origin', $origin);
            $response->setHeader('Vary', 'Origin');
        }

        // Allowed methods
        $methods = $config['allowed_methods'] ?? ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
        $response->setHeader('Access-Control-Allow-Methods', implode(', ', $methods));

        // Allowed headers
        $headers = $config['allowed_headers'] ?? ['Content-Type', 'Authorization'];
        $response->setHeader('Access-Control-Allow-Headers', implode(', ', $headers));

        // Exposed headers
        $exposed = $config['exposed_headers'] ?? [];
        if (!empty($exposed)) {
            $response->setHeader('Access-Control-Expose-Headers', implode(', ', $exposed));
        }

        // Credentials
        if ($allowCredentials) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Max age for preflight cache
        $maxAge = $config['max_age'] ?? 3600;
        $response->setHeader('Access-Control-Max-Age', (string) $maxAge);

        return $response;
    }
}
