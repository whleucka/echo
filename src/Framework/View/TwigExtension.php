<?php

namespace Echo\Framework\View;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtension extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction("csrf", [$this, "csrf"], ["is_safe" => ["html"]]),
            new TwigFunction("uri", [$this, "uri"]),
            new TwigFunction("old", [$this, "old"]),
            new TwigFunction("config", [$this, "config"]),
            new TwigFunction("php_ini", [$this, "phpIni"]),
            new TwigFunction("php_extensions", [$this, "phpExtensions"]),
        ];
    }

    public function csrf(): string
    {
        $token = session()->get("csrf_token");
        return twig()->render("components/csrf.html.twig", ["token" => $token]);
    }

    public function uri(string $name, array $params = [])
    {
        $path = uri($name, ...array_values($params));

        if ($path === null) {
            return null;
        }

        $routeSubdomain = router()->getRouteSubdomain($name);
        $currentSubdomain = request()->getSubdomain();

        if ($routeSubdomain && $routeSubdomain !== $currentSubdomain) {
            $appUrl = config('app.url') ?? 'http://localhost';
            $parsed = parse_url($appUrl);
            $scheme = $parsed['scheme'] ?? request()->getScheme();
            $host = $parsed['host'] ?? 'localhost';
            $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';

            return "{$scheme}://{$routeSubdomain}.{$host}{$port}{$path}";
        }

        return $path;
    }

    public function old(string $name, mixed $default = null)
    {
        return request()->request->get($name) ?? $default;
    }

    public function config(string $key, mixed $default = null): mixed
    {
        return config($key) ?? $default;
    }

    public function phpIni(string $name): ?string
    {
        return ini_get($name) ?: null;
    }

    public function phpExtensions(): array
    {
        return get_loaded_extensions();
    }
}
