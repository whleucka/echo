<?php

namespace Echo\Framework\Admin;

use App\Models\FileInfo;
use Echo\Framework\Admin\Schema\FieldDefinition;
use Echo\Framework\Admin\Schema\FormSchema;
use Echo\Framework\Http\RequestInterface;

class FormControlRenderer
{
    public function __construct(
        private FormSchema $formSchema,
        private string $moduleLink,
        private \Closure $renderer,
        private array $validationErrors,
        private RequestInterface $request,
        private PivotSyncer $pivotSyncer,
        private ?int $currentFormId = null,
    ) {}

    public function render(string $column, ?string $value, bool $forceReadonly = false, string $formType = 'create'): mixed
    {
        $field = $this->formSchema->getField($column);
        if (!$field) {
            return $value;
        }

        // Custom renderer takes priority
        if ($field->controlRenderer) {
            return ($field->controlRenderer)($column, $value);
        }

        $control = $field->control;

        return match ($control) {
            "input" => $this->renderControl("input", $field, $value, forceReadonly: $forceReadonly, formType: $formType),
            "number" => $this->renderControl("input", $field, $value, [
                "type" => "number",
            ], $forceReadonly, $formType),
            "checkbox" => $this->renderControl("input", $field, $value, [
                "value" => 1,
                "type" => "checkbox",
                "class" => "form-check-input ms-1",
                "checked" => $value != false,
            ], $forceReadonly, $formType),
            "email" => $this->renderControl("input", $field, $value, [
                "type" => "email",
                "autocomplete" => "email",
            ], $forceReadonly, $formType),
            "password" => $this->renderControl("input", $field, $value, [
                "type" => "password",
                "autocomplete" => "current-password",
            ], $forceReadonly, $formType),
            "dropdown" => $this->renderControl("dropdown", $field, $value, [
                "class" => "form-select",
                "options" => $field->resolveOptions(),
            ], $forceReadonly, $formType),
            "multiselect" => $this->renderControl("multiselect", $field, $value, [
                "class" => "form-select",
                "options" => $field->resolveOptions(),
                "selected" => $this->pivotSyncer->getValues($field, $this->currentFormId ?? 0),
            ], $forceReadonly, $formType),
            "textarea" => $this->renderControl("textarea", $field, $value, forceReadonly: $forceReadonly, formType: $formType),
            "editor" => $this->renderControl("editor", $field, $value, [
                "module" => ["link" => $this->moduleLink],
            ], $forceReadonly, $formType),
            "image", "file" => $this->renderFileControl($control, $field, $value, $forceReadonly, $formType),
            default => $value,
        };
    }

    private function renderFileControl(string $control, FieldDefinition $field, ?string $value, bool $forceReadonly, string $formType = 'create'): string
    {
        $fi = new FileInfo($value);
        $data = [
            "type" => "file",
            "file" => $fi ? $fi->getAttributes() : false,
            "accept" => $field->accept ?? ($control === 'image' ? 'image/*' : ''),
        ];
        if ($control === 'image') {
            $data["stored_name"] = $fi ? $fi->stored_name : false;
        }
        return $this->renderControl($control, $field, $value, $data, $forceReadonly, $formType);
    }

    private function renderControl(string $type, FieldDefinition $field, ?string $value, array $data = [], bool $forceReadonly = false, string $formType = 'create'): mixed
    {
        $required = $field->isRequired($formType);
        $column = $field->name;
        $default = [
            "type" => "input",
            "class" => "form-control",
            "v_class" => $this->getValidationClass($column, $required),
            "id" => $column,
            "name" => $column,
            "title" => $field->label,
            "value" => $value,
            "placeholder" => "",
            "datalist" => $field->datalist,
            "alt" => null,
            "minlength" => null,
            "maxlength" => null,
            "size" => null,
            "list" => null,
            "min" => null,
            "max" => null,
            "height" => null,
            "width" => null,
            "step" => null,
            "accpet" => null,
            "pattern" => null,
            "dirname" => null,
            "inputmode" => null,
            "autocomplete" => null,
            "checked" => null,
            "autofocus" => null,
            "required" => $required,
            "readonly" => $forceReadonly || $field->readonly,
            "disabled" => $forceReadonly || $field->disabled,
        ];
        $template_data = array_merge($default, $data);
        return ($this->renderer)("admin/controls/$type.html.twig", $template_data);
    }

    private function getValidationClass(string $column, bool $required): string
    {
        $request = $this->request->request;
        $classname = [];
        if (isset($request->$column) || $required && !isset($request->$column)) {
            $classname[] = isset($this->validationErrors[$column])
                ? 'is-invalid'
                : (isset($request->$column) ? 'is-valid' : '');
        }
        return implode(" ", $classname);
    }
}
