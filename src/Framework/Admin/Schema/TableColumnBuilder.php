<?php

namespace Echo\Framework\Admin\Schema;

class TableColumnBuilder
{
    private bool $searchable = false;
    private ?string $format = null;
    private ?\Closure $formatter = null;
    private ?string $cellTemplate = null;

    public function __construct(
        private string $name,
        private ?string $label = null,
        private ?string $expression = null,
    ) {
        $this->label = $label ?? ucfirst(str_replace('_', ' ', $name));
    }

    public function searchable(): self
    {
        $this->searchable = true;
        return $this;
    }

    /**
     * Named format: 'date', 'check', 'badge'
     */
    public function format(string $format): self
    {
        $this->format = $format;
        return $this;
    }

    /**
     * Custom formatter closure: fn(string $column, mixed $value): string
     */
    public function formatUsing(\Closure $formatter): self
    {
        $this->formatter = $formatter;
        return $this;
    }

    /**
     * Override the Twig template for this column's cell.
     */
    public function cellTemplate(string $template): self
    {
        $this->cellTemplate = $template;
        return $this;
    }

    public function build(): ColumnDefinition
    {
        return new ColumnDefinition(
            name: $this->name,
            label: $this->label,
            expression: $this->expression,
            searchable: $this->searchable,
            format: $this->format,
            formatter: $this->formatter,
            cellTemplate: $this->cellTemplate,
        );
    }
}
