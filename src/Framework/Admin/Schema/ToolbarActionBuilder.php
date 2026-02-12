<?php

namespace Echo\Framework\Admin\Schema;

class ToolbarActionBuilder
{
    private string $label;
    private string $icon;
    private ?string $permission;
    private bool $requiresForm;

    public function __construct(private string $name)
    {
        // Sensible defaults per built-in action type
        [$this->label, $this->icon, $this->permission, $this->requiresForm] = match ($name) {
            'create' => ['New',    'bi-plus-square', 'has_create', true],
            'export' => ['Export', 'bi-download',    'has_export', false],
            default  => [ucfirst($name), 'bi-gear', null, false],
        };
    }

    public function label(string $label): self
    {
        $this->label = $label;
        return $this;
    }

    public function icon(string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function permission(string $permission): self
    {
        $this->permission = $permission;
        return $this;
    }

    public function requiresForm(bool $requires = true): self
    {
        $this->requiresForm = $requires;
        return $this;
    }

    public function build(): ToolbarActionDefinition
    {
        return new ToolbarActionDefinition(
            name: $this->name,
            label: $this->label,
            icon: $this->icon,
            permission: $this->permission,
            requiresForm: $this->requiresForm,
        );
    }
}
