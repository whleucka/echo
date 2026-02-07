<?php

namespace Echo\Framework\Admin\Schema;

class FormSchemaBuilder
{
    private array $fields = [];

    /**
     * Define a form field.
     *
     * Usage:
     *   $builder->field('email', 'Email')->email()->rules(['required', 'email'])
     *   $builder->field('password', 'Password', "'' as password")->password()
     */
    public function field(string $name, ?string $label = null, ?string $expression = null): FormFieldBuilder
    {
        $fieldBuilder = new FormFieldBuilder($name, $label, $expression);
        $this->fields[] = $fieldBuilder;
        return $fieldBuilder;
    }

    /**
     * Build the immutable FormSchema.
     */
    public function build(): FormSchema
    {
        return new FormSchema(
            fields: array_map(fn(FormFieldBuilder $f) => $f->build(), $this->fields),
        );
    }
}
