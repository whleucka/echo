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

        // Handle preflight OPTIONS requests
        if ($request->getMethod() === 'OPTIONS') {
            return $this->preflightResponse();
        }

        // Process request and add CORS headers to response
        $response = $next($request);
        return $this->addCorsHeaders($response);
    }

    /**
     * Handle preflight OPTIONS request
     */
    private function preflightResponse(): ResponseInterface
    {
        $response = new HttpResponse('', 204);
        return $this->addCorsHeaders($response);
    }

    /**
     * Add CORS headers to response
     */
    private function addCorsHeaders(ResponseInterface $response): ResponseInterface
    {
        $config = config('cors');

        // Allowed origins
        $origins = $config['allowed_origins'] ?? ['*'];
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '*';

        if (in_array('*', $origins)) {
            $response->setHeader('Access-Control-Allow-Origin', '*');
        } elseif (in_array($origin, $origins)) {
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
        if ($config['allow_credentials'] ?? false) {
            $response->setHeader('Access-Control-Allow-Credentials', 'true');
        }

        // Max age for preflight cache
        $maxAge = $config['max_age'] ?? 3600;
        $response->setHeader('Access-Control-Max-Age', (string) $maxAge);

        return $response;
    }
}
