<?php

namespace Echo\Framework\Admin\Schema;

final class FieldDefinition
{
    public function __construct(
        public readonly string $name,
        public readonly string $label,
        public readonly ?string $expression,
        public readonly string $control,
        public readonly array $rules,
        public readonly array $options,
        public readonly ?string $optionsQuery,
        public readonly array $datalist,
        public readonly ?string $accept,
        public readonly mixed $default,
        public readonly bool $readonly,
        public readonly bool $disabled,
        public readonly bool $requiredOnCreate,
        public readonly ?\Closure $controlRenderer,
    ) {}

    /**
     * Get the SELECT expression for this field.
     * e.g., "'' as password" or just "email"
     */
    public function getSelectExpression(): string
    {
        if ($this->expression) {
            return $this->expression;
        }
        return $this->name;
    }

    /**
     * Check if this field has a specific validation rule.
     */
    public function hasRule(string $rule): bool
    {
        foreach ($this->rules as $r) {
            if (explode(':', $r, 2)[0] === explode(':', $rule, 2)[0]) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if this field is required for the given form type.
     */
    public function isRequired(string $formType = 'create'): bool
    {
        if ($this->requiredOnCreate && $formType !== 'create') {
            return false;
        }
        return in_array('required', $this->rules);
    }

    /**
     * Resolve dropdown options — either static array or dynamic SQL query.
     */
    public function resolveOptions(): array
    {
        if ($this->optionsQuery) {
            return db()->fetchAll($this->optionsQuery);
        }
        return $this->options;
    }
}
