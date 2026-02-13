<?php

namespace Echo\Framework\Admin\Schema;

class FormFieldBuilder
{
    private string $control = 'input';
    private array $rules = [];
    private array $options = [];
    private ?string $optionsQuery = null;
    private array $datalist = [];
    private ?string $accept = null;
    private mixed $default = null;
    private bool $readonly = false;
    private bool $disabled = false;
    private ?\Closure $controlRenderer = null;

    public function __construct(
        private string $name,
        private ?string $label = null,
        private ?string $expression = null,
    ) {
        $this->label = $label ?? ucfirst(str_replace('_', ' ', $name));
    }

    // Control type setters — each returns self for chaining

    public function input(): self
    {
        $this->control = 'input';
        return $this;
    }

    public function number(): self
    {
        $this->control = 'number';
        return $this;
    }

    public function checkbox(): self
    {
        $this->control = 'checkbox';
        return $this;
    }

    public function email(): self
    {
        $this->control = 'email';
        return $this;
    }

    public function password(): self
    {
        $this->control = 'password';
        return $this;
    }

    public function dropdown(): self
    {
        $this->control = 'dropdown';
        return $this;
    }

    public function image(): self
    {
        $this->control = 'image';
        return $this;
    }

    public function file(): self
    {
        $this->control = 'file';
        return $this;
    }

    public function textarea(): self
    {
        $this->control = 'textarea';
        return $this;
    }

    // Property setters

    public function rules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    public function options(array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function optionsFrom(string $query): self
    {
        $this->optionsQuery = $query;
        return $this;
    }

    public function datalist(array $values): self
    {
        $this->datalist = $values;
        return $this;
    }

    public function accept(string $accept): self
    {
        $this->accept = $accept;
        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        return $this;
    }

    public function readonly(): self
    {
        $this->readonly = true;
        return $this;
    }

    public function disabled(): self
    {
        $this->disabled = true;
        return $this;
    }

    public function renderUsing(\Closure $fn): self
    {
        $this->controlRenderer = $fn;
        return $this;
    }

    /**
     * Build the immutable FieldDefinition.
     */
    public function build(): FieldDefinition
    {
        return new FieldDefinition(
            name: $this->name,
            label: $this->label,
            expression: $this->expression,
            control: $this->control,
            rules: $this->rules,
            options: $this->options,
            optionsQuery: $this->optionsQuery,
            datalist: $this->datalist,
            accept: $this->accept,
            default: $this->default,
            readonly: $this->readonly,
            disabled: $this->disabled,
            controlRenderer: $this->controlRenderer,
        );
    }
}
