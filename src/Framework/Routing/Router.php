<?php

namespace Echo\Framework\Routing;

class Router implements RouterInterface
{
    private array $compiledPatterns = [];

    public function __construct(private Collector $collector)
    {
    }

    /**
     * Set pre-compiled patterns from cache
     */
    public function setCompiledPatterns(array $patterns): void
    {
        $this->compiledPatterns = $patterns;
    }

    /**
     * Search routes for URI by name
     */
    public function searchUri(string $name, ...$params): ?string
    {
        $routes = $this->collector->getRoutes();
        foreach ($routes as $uri => $route) {
            foreach ($route as $method => $info) {
                if ($info['name'] === $name) {
                    // Replace placeholders with actual values
                    $uri = preg_replace_callback('/\{(\w+)\}/', function ($matches) use (&$params) {
                        return array_shift($params) ?? $matches[0]; // Replace or keep original
                    }, $uri);
                    return $uri;
                }
            }
        }
        return null;
    }

    /**
     * Dispatch a new route
     */
    public function dispatch(string $uri, string $method, ?string $host = null): ?array
    {
        $method = strtolower($method);
        $routes = $this->collector->getRoutes();

        // Check for an exact match first
        if (isset($routes[$uri][$method])) {
            $route = $routes[$uri][$method];
            if (!$this->matchesSubdomain($route, $host)) {
                // Fall through to parameterized route check
            } else {
                $route['params'] = $this->getSubdomainParams($route, $host);
                return $route;
            }
        }

        // Check for parameterized routes
        foreach ($routes as $route => $methods) {
            // Use pre-compiled pattern if available, otherwise compile on the fly
            if (isset($this->compiledPatterns[$route])) {
                $pattern = $this->compiledPatterns[$route];
            } else {
                // Check if route has parameters or regex patterns
                $hasParams = str_contains($route, '{');
                $hasRegex = str_contains($route, '[') || str_contains($route, '(');

                if (!$hasParams && !$hasRegex) {
                    continue;
                }

                if ($hasParams) {
                    // Replace {param} placeholders with regex
                    $compiled = preg_replace('/\{(\w+)\}/', '([A-Za-z0-9_.-]+)', $route);
                    $pattern = "#^$compiled$#";
                } else {
                    // Route already contains regex pattern, just wrap it
                    $pattern = "#^$route$#";
                }
            }

            if (preg_match($pattern, $uri, $matches)) {
                // Ensure the requested method is valid for this route
                if (!isset($methods[$method])) {
                    return null;
                }

                // Check subdomain constraint
                if (!$this->matchesSubdomain($methods[$method], $host)) {
                    continue;
                }

                // Remove the full match
                array_shift($matches);
                // Add subdomain params before URI params
                $subdomainParams = $this->getSubdomainParams($methods[$method], $host);
                $methods[$method]['params'] = array_merge($subdomainParams, $matches);
                return $methods[$method];
            }
        }

        return null;
    }

    /**
     * Check if a route's subdomain constraint matches the given host
     */
    private function matchesSubdomain(array $route, ?string $host): bool
    {
        $subdomain = $route['subdomain'] ?? null;

        // No constraint — matches any host
        if ($subdomain === null) {
            return true;
        }

        if ($host === null) {
            return false;
        }

        // Strip port from host
        $host = strtok($host, ':');

        // Extract the leftmost label as the subdomain
        $parts = explode('.', $host);
        if (count($parts) < 2) {
            // Host has no subdomain (e.g. "localhost")
            return false;
        }

        $hostSubdomain = $parts[0];

        // Wildcard subdomain: {param} captures the value
        if (preg_match('/^\{(\w+)\}$/', $subdomain)) {
            return true;
        }

        // Exact match
        return $hostSubdomain === $subdomain;
    }

    /**
     * Get wildcard subdomain parameters from host
     */
    private function getSubdomainParams(array $route, ?string $host): array
    {
        $subdomain = $route['subdomain'] ?? null;
        if ($subdomain === null || $host === null) {
            return [];
        }

        if (preg_match('/^\{(\w+)\}$/', $subdomain)) {
            $host = strtok($host, ':');
            $parts = explode('.', $host);
            if (count($parts) >= 2) {
                return [$parts[0]];
            }
        }

        return [];
    }
}
