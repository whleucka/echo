<?php

namespace Echo\Framework\Admin\Schema;

final class ActionDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
    ) {}
}
