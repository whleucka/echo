<?php

namespace Echo\Framework\Routing;

interface RouterInterface
{
    public function dispatch(string $uri, string $method, ?string $host = null): ?array;
    public function searchUri(string $name, ...$params): ?string;
    public function getRouteSubdomain(string $name): ?string;
}
