<?php

namespace Echo\Framework\Admin\Schema;

class TableFilterBuilder
{
    private string $label;
    private string $type = 'dropdown';
    private array $options = [];
    private ?string $optionsQuery = null;

    public function __construct(
        private string $name,
        private string $column,
    ) {
        $this->label = ucfirst(str_replace('_', ' ', $name));
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function type(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    /**
     * Static options: [['value' => 'admin', 'label' => 'Admin'], ...]
     */
    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    /**
     * Dynamic options from SQL: "SELECT id as value, name as label FROM ..."
     */
    public function optionsFrom(string $query): self
    {
        $this->optionsQuery = $query;
        return $this;
    }

    public function build(): FilterDefinition
    {
        return new FilterDefinition(
            name: $this->name,
            column: $this->column,
            label: $this->label,
            type: $this->type,
            options: $this->options,
            optionsQuery: $this->optionsQuery,
        );
    }
}
