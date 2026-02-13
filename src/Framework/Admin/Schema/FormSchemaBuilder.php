<?php

namespace Echo\Framework\Admin\Schema;

class FormSchemaBuilder
{
    private array $fields = [];
    private ModalSize $modalSize = ModalSize::Default;

    /**
     * Define a form field.
     *
     * Usage:
     *   $builder->field('email', 'Email')->email()->rules(['required', 'email'])
     *   $builder->field('password', 'Password', "'' as password")->password()
     *
     * Hint:
     *   Missing a control type? Add control to FormFieldBuilder and ModuleController::control()
     */
    public function field(string $name, ?string $label = null, ?string $expression = null): FormFieldBuilder
    {
        $fieldBuilder = new FormFieldBuilder($name, $label, $expression);
        $this->fields[] = $fieldBuilder;
        return $fieldBuilder;
    }

    /**
     * Set the modal size for this form.
     *
     * Usage:
     *   $builder->modalSize(ModalSize::Large)
     */
    public function modalSize(ModalSize $size): self
    {
        $this->modalSize = $size;
        return $this;
    }

    /**
     * Build the immutable FormSchema.
     */
    public function build(): FormSchema
    {
        return new FormSchema(
            fields: array_map(fn(FormFieldBuilder $f) => $f->build(), $this->fields),
            modalSize: $this->modalSize,
        );
    }
}
