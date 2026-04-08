# Admin Modules

Admin modules extend `ModuleController` to provide CRUD interfaces with HTMX-driven tables, modal forms, sorting, filtering, pagination, CSV export, and per-user permissions.

## Quick Example

```php
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Admin\Schema\{TableSchemaBuilder, FormSchemaBuilder};
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(pathPrefix: "/products", namePrefix: "products")]
class ProductsController extends ModuleController
{
    protected string $tableName = "products";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC')->perPage(25);

        $builder->column('id', 'ID');
        $builder->column('name', 'Name')->searchable();
        $builder->column('price', 'Price');
        $builder->column('created_at', 'Created');

        $builder->rowAction('show');
        $builder->rowAction('edit');
        $builder->rowAction('delete');

        $builder->toolbarAction('create');
        $builder->toolbarAction('export');

        $builder->bulkAction('delete', 'Delete Selected');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->field('name', 'Name')->input()->rules(['required']);
        $builder->field('price', 'Price')->number()->rules(['required', 'numeric']);
        $builder->field('description', 'Description')->textarea();
    }
}
```

## Table Schema

### Columns

```php
$builder->column(string $name, ?string $label, ?string $expression)
```

- `$name` — column alias (used in SELECT AS)
- `$label` — display header (auto-generated from name if null)
- `$expression` — SQL expression (for computed columns, JOINs)

Column methods:

```php
->searchable()                              // include in fulltext search
->format(string $format)                    // named format: 'check', 'date', 'badge'
->formatUsing(Closure $fn)                  // custom: fn(string $col, mixed $val): string
->cellTemplate(string $template)            // override Twig cell template
```

### JOINs and Expressions

For complex queries with JOINs, use fully-qualified column names:

```php
$builder->primaryKey('audits.id')
    ->join('LEFT JOIN users ON users.id = audits.user_id')
    ->dateColumn('audits.created_at')
    ->defaultSort('audits.id', 'DESC');

$builder->column('id', 'ID', 'audits.id');
$builder->column('user_name', 'User',
    "COALESCE(CONCAT(users.first_name, ' ', users.surname), 'System')")
    ->searchable();
$builder->column('ip', 'IP', 'INET_NTOA(activity.ip)');
```

SQL expressions can include any valid SQL: `CONCAT()`, `COALESCE()`, `CASE WHEN`, `INET_NTOA()`, aggregate functions, etc.

### Sorting & Pagination

```php
$builder->defaultSort('id', 'DESC');        // default sort column and direction
$builder->perPage(50);                      // rows per page (default: 15)
$builder->dateColumn('audits.created_at');  // enable date range filter
$builder->primaryKey('audits.id');          // qualified key when using JOINs
```

### Dropdown Filters

```php
// Static options
$builder->filter('role', 'role')
    ->label('Role')
    ->options([
        ['value' => 'admin', 'label' => 'Admin'],
        ['value' => 'standard', 'label' => 'Standard'],
    ]);

// Dynamic options from SQL
$builder->filter('user', 'audits.user_id')
    ->label('User')
    ->optionsFrom("SELECT id as value, CONCAT(first_name, ' ', surname) as label FROM users ORDER BY label");
```

### Filter Links (Quick Filters)

Buttons above the table that apply/toggle a WHERE condition:

```php
$builder->filterLink('Created', "audits.event = 'created'");
$builder->filterLink('Updated', "audits.event = 'updated'");
$builder->filterLink('Deleted', "audits.event = 'deleted'");
$builder->filterLink('Unauthenticated', "user_id IS NULL");
```

### Row Actions

Per-row action buttons. Built-in names (`show`, `edit`, `delete`) have sensible defaults:

```php
$builder->rowAction('show');                // View — icon: bi-eye
$builder->rowAction('edit');                // Edit — icon: bi-pencil, permission: has_edit
$builder->rowAction('delete')              // Delete — icon: bi-trash, permission: has_delete
    ->confirm('Delete this record?');

// Custom action
$builder->rowAction('archive')
    ->label('Archive')
    ->icon('bi-archive')
    ->permission('has_edit');
```

Row action methods: `->label()`, `->icon()`, `->permission()`, `->requiresForm()`, `->confirm()`.

### Toolbar Actions

Top-level buttons. Built-in names (`create`, `export`) have defaults:

```php
$builder->toolbarAction('create');          // New — icon: bi-plus-square, permission: has_create
$builder->toolbarAction('export');          // Export — icon: bi-download, permission: has_export

// Custom
$builder->toolbarAction('import')
    ->label('Import')
    ->icon('bi-upload');
```

### Bulk Actions

Dropdown for actions on selected rows:

```php
$builder->bulkAction('delete', 'Delete Selected');
$builder->bulkAction('archive', 'Archive Selected');   // triggers handleTableAction()
```

## Form Schema

### Field Types

```php
$builder->field(string $name, ?string $label, ?string $expression)
```

Control types:

```php
->input()                // text input (default)
->number()               // numeric input
->email()                // email input
->password()             // password input (masked)
->checkbox()             // boolean (stored as 0/1)
->dropdown()             // select dropdown
->multiselect()          // multi-select (for pivot tables)
->textarea()             // large text area
->editor()               // rich HTML editor
->image()                // image upload/delete
->file()                 // file upload/delete
```

### Validation

```php
->rules(['required', 'email', 'unique:users', 'min_length:10', 'numeric', 'match:password'])
->requiredOnCreate()     // required on create, optional on edit
```

When `requiredOnCreate()` is used, the `required` rule is automatically removed during edit operations.

### Options & Datalists

```php
// Static options (dropdown/multiselect)
->options([
    ['value' => 'admin', 'label' => 'Admin'],
    ['value' => 'standard', 'label' => 'Standard'],
])

// Dynamic options from SQL
->optionsFrom("SELECT id as value, name as label FROM categories ORDER BY name")

// HTML5 datalist suggestions (input)
->datalist(['red', 'green', 'blue'])

// File accept attribute
->accept('image/*')
```

### Pivot Tables (Many-to-Many)

```php
$builder->field('tags', 'Tags')
    ->multiselect()
    ->optionsFrom("SELECT id as value, name as label FROM tags")
    ->pivot('post_tags', 'post_id', 'tag_id');
```

Pivot data is automatically synced in a transaction after the main record is saved.

### Other Field Options

```php
->readonly()             // display but cannot edit
->disabled()             // fully disabled
->default(mixed $value)  // default value for create forms
->renderUsing(Closure)   // custom control renderer
```

### Modal Size

```php
use Echo\Framework\Admin\Schema\ModalSize;

$builder->modalSize(ModalSize::Large);       // modal-lg
// Options: Small, Default, Large, ExtraLarge, Fullscreen
```

## Real-World Example: Users Module

```php
#[Group(pathPrefix: "/users", namePrefix: "users")]
class UsersController extends ModuleController
{
    protected string $tableName = "users";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC');

        $builder->column('id', 'ID');
        $builder->column('uuid', 'UUID');
        $builder->column('role', 'Role');
        $builder->column('name', 'Name', "CONCAT(first_name, ' ', surname)")->searchable();
        $builder->column('email', 'Email')->searchable();
        $builder->column('created_at', 'Created');

        $builder->filter('role', 'role')
            ->label('Role')
            ->options([
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'admin', 'label' => 'Admin'],
            ]);

        $builder->rowAction('show');
        $builder->rowAction('edit');
        $builder->rowAction('delete');
        $builder->toolbarAction('create');
        $builder->toolbarAction('export');
        $builder->bulkAction('delete', 'Delete');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->field('avatar', 'Avatar')->image()->accept('image/*');
        $builder->field('role', 'Role')
            ->dropdown()
            ->options([
                ['value' => 'standard', 'label' => 'Standard'],
                ['value' => 'admin', 'label' => 'Admin'],
            ])
            ->rules(['required']);
        $builder->field('first_name', 'First Name')->input()->rules(['required']);
        $builder->field('surname', 'Surname')->input();
        $builder->field('email', 'Email')->email()
            ->rules(['required', 'email', 'unique:users']);
        $builder->field('password', 'Password', "'' as password")
            ->password()
            ->requiredOnCreate()
            ->rules(['required', 'min_length:10', 'regex:^(?=.*[A-Z])(?=.*\\W)(?=.*\\d).+$']);
        $builder->field('password_match', 'Password (again)', "'' as password_match")
            ->password()
            ->requiredOnCreate()
            ->rules(['required', 'match:password']);
    }

    // Prevent self-deletion
    protected function hasDelete(int $id): bool
    {
        if ($id === $this->user->id) return false;
        return parent::hasDelete($id);
    }

    // Hash password on create
    protected function handleStore(array $request): mixed
    {
        $service = container()->get(AuthService::class);
        unset($request['password_match']);
        $request['password'] = $service->hashPassword($request['password']);
        return parent::handleStore($request);
    }

    // Hash password on update (only if provided)
    protected function handleUpdate(int $id, array $request): bool
    {
        unset($request['password_match']);
        if (!empty($request['password'])) {
            $service = container()->get(AuthService::class);
            $request['password'] = $service->hashPassword($request['password']);
        } else {
            unset($request['password']);
        }
        return parent::handleUpdate($id, $request);
    }
}
```

## Hooks

Override these methods to customize behavior:

| Method | Purpose |
|---|---|
| `init()` | Runs on construction — loads module metadata, checks permissions |
| `handleStore(array $request): mixed` | Insert logic — return new ID or false |
| `handleUpdate(int $id, array $request): bool` | Update logic — return success |
| `handleDestroy(int $id): bool` | Delete logic — return success |
| `handleTableAction(int $id, string $action)` | Handle custom bulk actions |
| `formatRow(array $row): array` | Transform each row before rendering |
| `formOverride(?int $id, array $form): array` | Modify form data before rendering |
| `exportOverride(array $row): array` | Modify row data for CSV export |

## Permissions

Permissions are managed per-module in the `modules` database table. Admin users bypass all checks. Non-admin users require specific flags:

- `has_create` — can create new records
- `has_edit` — can edit existing records
- `has_delete` — can delete records
- `has_export` — can export CSV

Custom permissions can be checked via `isActionAllowed()` and `isToolbarActionAllowed()`.

## Built-in Modules

| Module | Path | Description |
|---|---|---|
| Users | `/admin/users` | User management with role filtering |
| Audits | `/admin/audits` | Audit log with diff viewer, JOINs |
| Activity | `/admin/activity` | HTTP request logs with GeoIP |
| Health | `/admin/health` | System health dashboard |
| Modules | `/admin/modules` | Module permission management |
| User Permissions | `/admin/user-permissions` | Per-user module permissions |
| File Info | `/admin/file-info` | Uploaded file management |
