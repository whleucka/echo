# v0.4.0 Spec: Declarative Form Schema

## Background

v0.3.0 replaced the array-based table config with type-safe `TableSchema` + `TableSchemaBuilder`. Forms still use the old array properties inherited from the deleted `AdminController`. This spec covers replacing them with the same builder/value-object pattern.

## Current State (v0.3.0)

Form configuration lives as 8 scattered arrays on `ModuleController`:

```php
// Every form-bearing controller sets these in its constructor
$this->form_columns      = ["Label" => "column_or_expression"];
$this->form_controls     = ["column" => "input|dropdown|checkbox|..."];
$this->form_dropdowns    = ["column" => [...options...] | "SELECT ..."];
$this->form_datalist     = ["column" => [...values...]];
$this->form_readonly     = ["column", ...];
$this->form_disabled     = ["column", ...];
$this->form_defaults     = ["column" => "default_value"];
$this->file_accept       = ["column" => "image/*"];
$this->validation_rules  = ["column" => ["required", "email", ...]];
```

These must stay in sync manually. A typo in any array silently breaks the form.

## Goal

Replace all form arrays with a single `defineForm(FormSchemaBuilder)` method:

```php
protected function defineForm(FormSchemaBuilder $builder): void
{
    $builder->field('role', 'Role')
            ->dropdown()
            ->options([
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'admin', 'label' => 'Admin'],
            ])
            ->rules(['required']);

    $builder->field('first_name', 'First Name')
            ->input()
            ->rules(['required']);

    $builder->field('email', 'Email')
            ->email()
            ->rules(['required', 'email', 'unique:users']);

    $builder->field('password', 'Password')
            ->password()
            ->rules(['required', 'min_length:10', 'regex:^(?=.*[A-Z])(?=.*\W)(?=.*\d).+$']);

    $builder->field('avatar', 'Avatar')
            ->image()
            ->accept('image/*');
}
```

---

## Value Objects

### `FieldDefinition`

```php
namespace Echo\Framework\Admin\Schema;

final class FieldDefinition
{
    public function __construct(
        public readonly string $name,          // DB column name
        public readonly string $label,         // Display label
        public readonly ?string $expression,   // SQL expression for SELECT (e.g., "'' as password")
        public readonly string $control,       // Control type: input, number, checkbox, email, password, dropdown, image, file
        public readonly array $rules,          // Validation rules: ['required', 'email', 'unique:users', ...]
        public readonly array $options,        // Dropdown options: [['value' => ..., 'label' => ...], ...]
        public readonly ?string $optionsQuery, // SQL to fetch dropdown options dynamically
        public readonly array $datalist,       // Datalist values for input autocomplete
        public readonly ?string $accept,       // File accept attribute (e.g., "image/*")
        public readonly mixed $default,        // Default value for create forms
        public readonly bool $readonly,
        public readonly bool $disabled,
        public readonly ?\Closure $controlRenderer, // Custom control renderer: fn(string $column, mixed $value): string
    ) {}
}
```

### `FormSchema`

```php
namespace Echo\Framework\Admin\Schema;

final class FormSchema
{
    /**
     * @param FieldDefinition[] $fields
     */
    public function __construct(
        public readonly array $fields,
    ) {}

    /** Get field by column name */
    public function getField(string $name): ?FieldDefinition { ... }

    /** Get validation rules array compatible with Controller::validate() */
    public function getValidationRules(): array
    {
        $rules = [];
        foreach ($this->fields as $field) {
            $rules[$field->name] = $field->rules;
        }
        return $rules;
    }

    /** Get SELECT expressions for form query */
    public function getSelectExpressions(): array
    {
        return array_map(function (FieldDefinition $field) {
            if ($field->expression) {
                return "{$field->expression} as {$field->name}";
            }
            return $field->name;
        }, $this->fields);
    }

    /** Get labels keyed by position (for form-modal.html.twig) */
    public function getLabels(): array
    {
        return array_map(fn(FieldDefinition $f) => $f->label, $this->fields);
    }

    /** Get default values for create forms */
    public function getDefaults(): array
    {
        $defaults = [];
        foreach ($this->fields as $field) {
            $defaults[$field->name] = $field->default;
        }
        return $defaults;
    }
}
```

---

## Builders

### `FormFieldBuilder`

```php
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
    public function input(): self       { $this->control = 'input'; return $this; }
    public function number(): self      { $this->control = 'number'; return $this; }
    public function checkbox(): self    { $this->control = 'checkbox'; return $this; }
    public function email(): self       { $this->control = 'email'; return $this; }
    public function password(): self    { $this->control = 'password'; return $this; }
    public function dropdown(): self    { $this->control = 'dropdown'; return $this; }
    public function image(): self       { $this->control = 'image'; return $this; }
    public function file(): self        { $this->control = 'file'; return $this; }

    public function rules(array $rules): self           { $this->rules = $rules; return $this; }
    public function options(array $options): self        { $this->options = $options; return $this; }
    public function optionsFrom(string $query): self    { $this->optionsQuery = $query; return $this; }
    public function datalist(array $values): self       { $this->datalist = $values; return $this; }
    public function accept(string $accept): self        { $this->accept = $accept; return $this; }
    public function default(mixed $value): self         { $this->default = $value; return $this; }
    public function readonly(): self                    { $this->readonly = true; return $this; }
    public function disabled(): self                    { $this->disabled = true; return $this; }
    public function renderUsing(\Closure $fn): self     { $this->controlRenderer = $fn; return $this; }

    public function build(): FieldDefinition { ... }
}
```

### `FormSchemaBuilder`

```php
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

    public function build(): FormSchema
    {
        return new FormSchema(
            fields: array_map(fn(FormFieldBuilder $f) => $f->build(), $this->fields),
        );
    }
}
```

---

## ModuleController Changes

### New abstract method

```php
// Optional — modules without forms don't need to implement this
protected function defineForm(FormSchemaBuilder $builder): void {}
```

### Constructor builds form schema

```php
// In ModuleController::__construct()
$formBuilder = new FormSchemaBuilder();
$this->defineForm($formBuilder);
$this->formSchema = $formBuilder->build();
```

### Form arrays replaced

All 8 form array properties are removed from `ModuleController`. The `control()`, `renderControl()`, `renderForm()`, `runFormQuery()`, and `massageRequest()` methods are rewritten to consume `FormSchema` and `FieldDefinition` instead of arrays.

### Validation rules come from schema

```php
// Before (v0.3.0)
$valid = $this->validate($this->validation_rules);

// After (v0.4.0)
$valid = $this->validate($this->formSchema->getValidationRules());
```

### Permission checks use schema

```php
// Before
protected function hasCreate(): bool
{
    return $this->checkPermission('has_create') && $this->has_create && !empty($this->form_columns);
}

// After
protected function hasCreate(): bool
{
    return $this->checkPermission('has_create') && $this->has_create && !empty($this->formSchema->fields);
}
```

---

## Migration Example: UsersController

### Before (v0.3.0)

```php
public function __construct()
{
    $this->form_columns = [
        "Avatar" => "avatar",
        "Role" => "role",
        "First Name" => "first_name",
        "Surname" => "surname",
        "Email" => "email",
        "Password" => "'' as password",
        "Password (again)" => "'' as password_match",
    ];
    $this->form_controls = [
        "avatar" => "image",
        "role" => "dropdown",
        "first_name" => "input",
        "surname" => "input",
        "email" => "email",
        "password" => "password",
        "password_match" => "password",
    ];
    $this->form_dropdowns = [
        "role" => [
            ["value" => "standard", "label" => "Standard"],
            ["value" => "admin", "label" => "Admin"],
        ]
    ];
    $this->validation_rules = [
        "avatar" => [],
        "role" => ["required"],
        "first_name" => ["required"],
        "surname" => [],
        "email" => ["required", "email", "unique:users"],
        "password" => ["required", "min_length:10", "regex:..."],
        "password_match" => ["required", "match:password"],
    ];
    parent::__construct("users");
}
```

### After (v0.4.0)

```php
protected function defineForm(FormSchemaBuilder $builder): void
{
    $builder->field('avatar', 'Avatar')
            ->image()
            ->accept('image/*');

    $builder->field('role', 'Role')
            ->dropdown()
            ->options([
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'admin', 'label' => 'Admin'],
            ])
            ->rules(['required']);

    $builder->field('first_name', 'First Name')
            ->input()
            ->rules(['required']);

    $builder->field('surname', 'Surname')
            ->input();

    $builder->field('email', 'Email')
            ->email()
            ->rules(['required', 'email', 'unique:users']);

    $builder->field('password', 'Password', "'' as password")
            ->password()
            ->rules(['required', 'min_length:10', 'regex:^(?=.*[A-Z])(?=.*\W)(?=.*\d).+$']);

    $builder->field('password_match', 'Password (again)', "'' as password_match")
            ->password()
            ->rules(['required', 'match:password']);
}

public function __construct()
{
    parent::__construct("users");
}
```

The constructor goes from 40 lines to 1.

---

## Migration Example: ModulesController

```php
protected function defineForm(FormSchemaBuilder $builder): void
{
    $builder->field('enabled', 'Enabled')->checkbox();
    $builder->field('parent_id', 'Parent')
            ->dropdown()
            ->optionsFrom("SELECT id as value, if(parent_id IS NULL, concat(title, ' (root)'), title) as label
                FROM modules ORDER BY parent_id IS NULL DESC, title");
    $builder->field('link', 'Link')->input();
    $builder->field('title', 'Title')->input()->rules(['required']);
    $builder->field('icon', 'Icon')->input()->datalist($this->getIconList());
    $builder->field('item_order', 'Order')->number();
}
```

---

## File Structure

New files:

```
src/Framework/Admin/Schema/
├── FieldDefinition.php
├── FormSchema.php
├── FormSchemaBuilder.php
└── FormFieldBuilder.php
```

Modified files:

```
src/Framework/Http/ModuleController.php  — replace form arrays with FormSchema
app/Http/Controllers/Admin/*             — migrate defineForm() for each controller
```

## Implementation Order

| Step | What | Notes |
|------|------|-------|
| 1 | `FieldDefinition` + `FormSchema` | Value objects. Write tests. |
| 2 | `FormFieldBuilder` + `FormSchemaBuilder` | Builder pipeline. Write tests. |
| 3 | Update `ModuleController` | Rewrite form methods to use `FormSchema`. Remove array properties. |
| 4 | Migrate `UsersController` | First migration — validates the pipeline. |
| 5 | Migrate remaining controllers | `ModulesController`, `UserPermissionsController`. |
| 6 | Template cleanup | Update `form-modal.html.twig` if needed (likely minimal). |

## Out of Scope

- Custom form layouts (multi-column, tabs, sections) — future enhancement
- Client-side validation — stays server-side only
- AJAX form submission changes — HTMX flow stays identical
