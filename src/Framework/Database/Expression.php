<?php

namespace Echo\Framework\Database;

/**
 * Represents a raw SQL expression that should not be parameterized.
 *
 * Use QueryBuilder::raw() to create instances.
 */
class Expression
{
    public function __construct(
        public readonly string $value,
        public readonly array $bindings = []
    ) {
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
