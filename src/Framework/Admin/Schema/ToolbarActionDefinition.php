<?php

namespace Echo\Framework\Admin\Schema;

final class ToolbarActionDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly string $icon,
        public readonly ?string $permission,
        public readonly bool $requiresForm,
    ) {}
}
