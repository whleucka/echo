<?php

namespace Echo\Framework\Routing;

interface RouterInterface
{
    public function dispatch(string $uri, string $method): ?array;
    public function searchUri(string $name, ...$params): ?string;
}
