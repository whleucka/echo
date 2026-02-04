<?php

namespace Echo\Framework\Http\Middleware;

use Closure;
use Echo\Interface\Http\{Request, Middleware, Response};

/**
 * API Versioning Middleware
 * Parses and sets API version from headers or URL
 */
class ApiVersion implements Middleware
{
    private const DEFAULT_VERSION = 'v1';
    private const SUPPORTED_VERSIONS = ['v1'];

    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->getAttribute("route");
        $middleware = $route["middleware"] ?? [];

        // Only apply to API routes
        if (!in_array('api', $middleware)) {
            return $next($request);
        }

        // Determine API version
        $version = $this->parseVersion($request);
        $request->setAttribute('api_version', $version);

        // Process request
        $response = $next($request);

        // Add version header to response
        $response->setHeader('X-API-Version', $version);

        return $response;
    }

    /**
     * Parse API version from request
     * Priority: Header > URL > Default
     */
    private function parseVersion(Request $request): string
    {
        // 1. Check Accept header: application/vnd.echo.v1+json
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (preg_match('/application\/vnd\.echo\.(v\d+)\+json/i', $accept, $matches)) {
            $version = strtolower($matches[1]);
            if ($this->isSupported($version)) {
                return $version;
            }
        }

        // 2. Check custom X-API-Version header
        $headerVersion = $_SERVER['HTTP_X_API_VERSION'] ?? null;
        if ($headerVersion && $this->isSupported($headerVersion)) {
            return strtolower($headerVersion);
        }

        // 3. Check URL path for version: /api/v1/users
        $uri = $request->getUri();
        if (preg_match('/\/api\/(v\d+)\//i', $uri, $matches)) {
            $version = strtolower($matches[1]);
            if ($this->isSupported($version)) {
                return $version;
            }
        }

        // 4. Default version
        return self::DEFAULT_VERSION;
    }

    /**
     * Check if version is supported
     */
    private function isSupported(string $version): bool
    {
        return in_array(strtolower($version), self::SUPPORTED_VERSIONS);
    }

    /**
     * Get list of supported versions
     */
    public static function getSupportedVersions(): array
    {
        return self::SUPPORTED_VERSIONS;
    }
}
