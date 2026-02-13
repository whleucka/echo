<?php

namespace Echo\Framework\Admin\Schema;

final class ColumnDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $expression,
        public readonly bool $searchable,
        public readonly ?string $format,
        public readonly ?\Closure $formatter,
        public readonly ?string $cellTemplate,
    ) {}

    /**
     * Get the SELECT expression for this column.
     * e.g., "CONCAT(first_name, ' ', surname) as name" or just "email"
     */
    public function getSelectExpression(): string
    {
        if ($this->expression) {
            return "{$this->expression} as {$this->name}";
        }
        return $this->name;
    }
}
