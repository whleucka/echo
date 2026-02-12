<?php

namespace Echo\Framework\Admin\Schema;

class RowActionBuilder
{
    private string $label;
    private string $icon;
    private ?string $permission;
    private bool $requiresForm;
    private ?string $confirm;

    public function __construct(private string $name)
    {
        // Sensible defaults per built-in action type
        [$this->label, $this->icon, $this->permission, $this->requiresForm, $this->confirm] = match ($name) {
            'show'   => ['View',   'bi-eye',    null,         true,  null],
            'edit'   => ['Edit',   'bi-pencil', 'has_edit',   true,  null],
            'delete' => ['Delete', 'bi-trash',  'has_delete', false, 'Please confirm deletion'],
            default  => [ucfirst($name), 'bi-gear', null, false, null],
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

    public function confirm(?string $message): self
    {
        $this->confirm = $message;
        return $this;
    }

    public function build(): RowActionDefinition
    {
        return new RowActionDefinition(
            name: $this->name,
            label: $this->label,
            icon: $this->icon,
            permission: $this->permission,
            requiresForm: $this->requiresForm,
            confirm: $this->confirm,
        );
    }
}
