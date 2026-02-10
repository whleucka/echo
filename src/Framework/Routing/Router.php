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
    public function dispatch(string $uri, string $method): ?array
    {
        $method = strtolower($method);
        $routes = $this->collector->getRoutes();

        // Check for an exact match first
        if (isset($routes[$uri][$method])) {
            // There are no params
            $routes[$uri][$method]['params'] = [];
            return $routes[$uri][$method];
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

                // Remove the full match
                array_shift($matches);
                // Add available params
                $methods[$method]['params'] = $matches;
                return $methods[$method];
            }
        }

        return null;
    }
}
