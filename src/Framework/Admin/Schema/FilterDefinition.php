<?php

namespace Echo\Framework\Admin\Schema;

final class FilterDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $column,
        public readonly string $label,
        public readonly string $type,
        public readonly array $options,
        public readonly ?string $optionsQuery,
    ) {}

    /**
     * Resolve options — returns static options or fetches from SQL query.
     */
    public function resolveOptions(): array
    {
        if ($this->optionsQuery) {
            return db()->fetchAll($this->optionsQuery) ?: [];
        }
        return $this->options;
    }
}
