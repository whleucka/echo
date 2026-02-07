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
            new TwigFunction("php_ini", [$this, "phpIni"]),
            new TwigFunction("php_extensions", [$this, "phpExtensions"]),
        ];
    }

    public function csrf(): string
    {
        $token = session()->get("csrf_token");
        return twig()->render("components/csrf.html.twig", ["token" => $token]);
    }

    public function uri(string $name)
    {
        return uri($name);
    }

    public function old(string $name, mixed $default = null)
    {
        return request()->request->get($name) ?? $default;
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
