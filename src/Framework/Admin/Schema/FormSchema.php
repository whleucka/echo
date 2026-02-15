<?php

namespace Echo\Framework\Admin\Schema;

final class FormSchema
{
    /**
     * @param FieldDefinition[] $fields
     */
    public function __construct(
        public readonly array $fields,
        public readonly ModalSize $modalSize = ModalSize::Default,
    ) {}

    /**
     * Get field by column name.
     */
    public function getField(string $name): ?FieldDefinition
    {
        foreach ($this->fields as $field) {
            if ($field->name === $name) {
                return $field;
            }
        }
        return null;
    }

    /**
     * Get validation rules array compatible with Controller::validate().
     *
     * @return array<string, string[]>
     */
    public function getValidationRules(string $formType = 'create'): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $fieldRules = $field->rules;
            if ($field->requiredOnCreate && $formType !== 'create') {
                $fieldRules = array_values(array_filter(
                    $fieldRules,
                    fn(string $rule) => $rule !== 'required'
                ));
            }
            $rules[$field->name] = $fieldRules;
        }
        return $rules;
    }

    /**
     * Get SELECT expressions for form query.
     * Excludes pivot fields as they don't exist in the main table.
     *
     * @return string[]
     */
    public function getSelectExpressions(): array
    {
        return array_values(array_map(
            fn(FieldDefinition $field) => $field->getSelectExpression(),
            array_filter($this->fields, fn(FieldDefinition $field) => !$field->hasPivot())
        ));
    }

    /**
     * Get labels indexed by position (for form-modal.html.twig).
     *
     * @return string[]
     */
    public function getLabels(): array
    {
        return array_map(fn(FieldDefinition $f) => $f->label, $this->fields);
    }

    /**
     * Get default values for create forms.
     *
     * @return array<string, mixed>
     */
    public function getDefaults(): array
    {
        $defaults = [];
        foreach ($this->fields as $field) {
            $defaults[$field->name] = $field->default;
        }
        return $defaults;
    }
}
