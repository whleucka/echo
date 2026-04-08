<?php

namespace Echo\Framework\Routing;

class Router implements RouterInterface
{
    private array $compiledPatterns = [];
    private ?array $registeredSubdomains = null;

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
        foreach ($routes as $uri => $methodGroups) {
            foreach ($methodGroups as $candidates) {
                foreach ($candidates as $info) {
                    if ($info['name'] === $name) {
                        $uri = preg_replace_callback('/\{(\w+)\}/', function ($matches) use (&$params) {
                            return array_shift($params) ?? $matches[0];
                        }, $uri);
                        return $uri;
                    }
                }
            }
        }
        return null;
    }

    /**
     * Get the subdomain constraint for a named route
     */
    public function getRouteSubdomain(string $name): ?string
    {
        $routes = $this->collector->getRoutes();
        foreach ($routes as $methodGroups) {
            foreach ($methodGroups as $candidates) {
                foreach ($candidates as $info) {
                    if ($info['name'] === $name) {
                        return $info['subdomain'] ?? null;
                    }
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
            $matched = $this->findBestSubdomainMatch($routes[$uri][$method], $host);
            if ($matched) {
                $matched['params'] = $this->getSubdomainParams($matched, $host);
                return $matched;
            }
        }

        // Check for parameterized routes
        foreach ($routes as $route => $methodGroups) {
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
                    $compiled = preg_replace('/\{(\w+)\}/', '([A-Za-z0-9_.-]+)', $route);
                    $pattern = "#^$compiled$#";
                } else {
                    $pattern = "#^$route$#";
                }
            }

            if (preg_match($pattern, $uri, $matches)) {
                // Ensure the requested method is valid for this route
                if (!isset($methodGroups[$method])) {
                    return null;
                }

                // Find best subdomain match from candidates
                $matched = $this->findBestSubdomainMatch($methodGroups[$method], $host);
                if (!$matched) {
                    continue;
                }

                // Remove the full match
                array_shift($matches);
                // Add subdomain params before URI params
                $subdomainParams = $this->getSubdomainParams($matched, $host);
                $matched['params'] = array_merge($subdomainParams, $matches);
                return $matched;
            }
        }

        return null;
    }

    /**
     * Find the best subdomain match from a list of route candidates.
     * Priority: exact subdomain > wildcard subdomain > unconstrained (null)
     */
    private function findBestSubdomainMatch(array $candidates, ?string $host): ?array
    {
        $unconstrained = null;
        $wildcard = null;

        foreach ($candidates as $route) {
            if (!$this->matchesSubdomain($route, $host)) {
                continue;
            }

            $subdomain = $route['subdomain'] ?? null;
            if ($subdomain === null) {
                $unconstrained = $route;
            } elseif (preg_match('/^\{(\w+)\}$/', $subdomain)) {
                $wildcard = $route;
            } else {
                // Exact subdomain match — highest priority
                return $route;
            }
        }

        return $wildcard ?? $unconstrained;
    }

    /**
     * Check if a route's subdomain constraint matches the given host
     */
    private function matchesSubdomain(array $route, ?string $host): bool
    {
        $subdomain = $route['subdomain'] ?? null;

        // No constraint — matches hosts without a registered subdomain
        if ($subdomain === null) {
            if ($host !== null) {
                $hostSub = $this->extractSubdomain($host);
                if ($hostSub !== null && isset($this->getRegisteredSubdomains()[$hostSub])) {
                    return false;
                }
            }
            return true;
        }

        if ($host === null) {
            return false;
        }

        $hostSub = $this->extractSubdomain($host);
        if ($hostSub === null) {
            return false;
        }

        // Wildcard subdomain: {param} captures the value
        if (preg_match('/^\{(\w+)\}$/', $subdomain)) {
            return true;
        }

        // Exact match
        return $hostSub === $subdomain;
    }

    /**
     * Extract the subdomain label from a host string
     */
    private function extractSubdomain(string $host): ?string
    {
        $host = strtok($host, ':');
        $parts = explode('.', $host);
        if (count($parts) < 2) {
            return null;
        }
        return $parts[0];
    }

    /**
     * Build a set of all explicitly registered subdomain constraints
     */
    private function getRegisteredSubdomains(): array
    {
        if ($this->registeredSubdomains === null) {
            $this->registeredSubdomains = [];
            foreach ($this->collector->getRoutes() as $methodGroups) {
                foreach ($methodGroups as $candidates) {
                    foreach ($candidates as $route) {
                        $sub = $route['subdomain'] ?? null;
                        if ($sub !== null && !preg_match('/^\{(\w+)\}$/', $sub)) {
                            $this->registeredSubdomains[$sub] = true;
                        }
                    }
                }
            }
        }
        return $this->registeredSubdomains;
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
