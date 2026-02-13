<?php

namespace Echo\Framework\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public string $path,
        public ?string $name = null,
        public array $middleware = [],
        public ?string $subdomain = null
    ) {
    }
}
