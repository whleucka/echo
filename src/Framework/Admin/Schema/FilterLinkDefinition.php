<?php

namespace Echo\Framework\Admin\Schema;

final class FilterLinkDefinition
{
    public function __construct(
        public readonly string $label,
        public readonly string $condition,
    ) {}
}
