# v0.3.0 Refactoring Proposal: Declarative Admin Modules

## Problem Statement

The current `AdminController` (`src/Framework/Http/AdminController.php`, 1215 lines) is a monolith that handles table rendering, form rendering, filtering, CRUD operations, validation, file uploads, CSV export, permissions, session state, and pagination — all in a single abstract class.

Admin modules configure it via stringly-typed arrays:

```php
// No type safety, no IDE support, easy to mistype keys
$this->table_columns = ["Name" => "CONCAT(first_name, ' ', surname) as name"];
$this->form_controls = ["role" => "dropdown"];
$this->filter_dropdowns = ["role" => [["value" => "admin", "label" => "Admin"]]];
$this->table_format = ["event" => fn($col, $val) => $this->formatEvent($val)];
```

This causes:
- No IDE autocompletion or static analysis on config arrays
- Column/format/control definitions scattered across separate arrays that must stay in sync
- The `format()` and `control()` methods use stringly-typed matching (`"check"`, `"dropdown"`, `"image"`)
- `registerFunctions()` pollutes the global Twig environment with per-controller functions
- Raw SQL is embedded directly in controller constructors (`CONCAT(...)`, `LEFT JOIN`, `INET_NTOA(...)`)
- Session state, query building, rendering, and CRUD are all entangled in one class
- Adding a new table feature (e.g., row-level styling, computed columns) requires modifying AdminController itself

## Goals for v0.3.0

1. **Type-safe schema definitions** — replace array config with fluent builders that produce value objects
2. **Separation of concerns** — extract data fetching, state management, and rendering into focused components
3. **Preserve what works** — keep HTMX integration, attribute routing, permission system, existing Twig templates
4. **Gradual migration** — new system runs alongside the old `AdminController`; controllers migrate one at a time
5. **No scope creep** — focus on Tables and Filters first; Forms are a follow-up

## Architecture Overview

```
CURRENT (v0.2.x)                         NEW (v0.3.0)
─────────────────                         ───────────
AdminController (1215 lines)    →    ModuleController (routing + CRUD orchestration)
  ├─ table_columns[]                   ├─ TableSchema (value objects via builder)
  ├─ table_format[]                    │    ├─ ColumnDefinition[]
  ├─ table_actions[]                   │    ├─ FilterDefinition[]
  ├─ filter_dropdowns[]                │    ├─ ActionDefinition[]
  ├─ search_columns[]                  │    └─ PaginationConfig
  ├─ form_columns[]                    ├─ FormSchema (Phase 2 — not in v0.3.0)
  ├─ form_controls[]                   ├─ TableDataSource (interface)
  ├─ validation_rules[]                │    └─ QueryBuilderDataSource (default impl)
  ├─ renderTable()                     ├─ TableRenderer
  ├─ renderForm()                      └─ ModuleState (session management)
  ├─ renderFilter()
  ├─ handleStore/Update/Destroy()
  ├─ session management
  └─ permission checks
```

---

## Phase 1: Value Objects

Value objects are immutable data carriers. Builders produce them; they replace the raw arrays.

### `ColumnDefinition`

```php
namespace Echo\Framework\Admin\Schema;

final class ColumnDefinition
{
    public function __construct(
        public readonly string $name,         // DB column or expression alias
        public readonly string $label,        // Display header
        public readonly ?string $expression,  // Raw SQL expression (e.g., "CONCAT(first_name, ' ', surname)")
        public readonly bool $sortable,
        public readonly bool $searchable,
        public readonly ?string $format,      // Named format: 'date', 'check', 'badge', or null
        public readonly ?\Closure $formatter, // Custom formatter: fn(string $column, mixed $value): string
        public readonly ?string $cellTemplate, // Twig template override for this cell
    ) {}
}
```

### `FilterDefinition`

```php
namespace Echo\Framework\Admin\Schema;

final class FilterDefinition
{
    public function __construct(
        public readonly string $name,       // Filter identifier
        public readonly string $column,     // DB column to filter on
        public readonly string $label,      // Display label
        public readonly string $type,       // 'dropdown', 'text', 'date_range'
        public readonly array $options,     // For dropdown: [['value' => ..., 'label' => ...], ...]
        public readonly ?string $optionsQuery, // SQL to fetch options dynamically
    ) {}
}
```

### `ActionDefinition`

```php
namespace Echo\Framework\Admin\Schema;

final class ActionDefinition
{
    /**
     * Bulk action (shown in table header dropdown when rows are selected).
     */
    public function __construct(
        public readonly string $name,   // Action identifier (e.g., 'delete', 'archive')
        public readonly string $label,  // Display label
    ) {}
}
```

### `FilterLinkDefinition`

```php
namespace Echo\Framework\Admin\Schema;

final class FilterLinkDefinition
{
    /**
     * Quick-filter button (e.g., "Created", "Updated", "Deleted" on AuditController).
     */
    public function __construct(
        public readonly string $label,     // Button label
        public readonly string $condition, // SQL WHERE condition
    ) {}
}
```

### `PaginationConfig`

```php
namespace Echo\Framework\Admin\Schema;

final class PaginationConfig
{
    public function __construct(
        public readonly int $perPage = 10,
        public readonly array $perPageOptions = [10, 25, 50, 100],
        public readonly int $paginationLinks = 2,
    ) {}
}
```

### `TableSchema`

The assembled schema — what the builder produces.

```php
namespace Echo\Framework\Admin\Schema;

final class TableSchema
{
    /**
     * @param ColumnDefinition[]     $columns
     * @param FilterDefinition[]     $filters
     * @param FilterLinkDefinition[] $filterLinks
     * @param ActionDefinition[]     $actions
     * @param string[]               $joins       Raw SQL JOIN clauses
     */
    public function __construct(
        public readonly string $table,
        public readonly string $primaryKey,
        public readonly array $columns,
        public readonly array $filters,
        public readonly array $filterLinks,
        public readonly array $actions,
        public readonly array $joins,
        public readonly string $defaultOrderBy,
        public readonly string $defaultSort,
        public readonly string $dateColumn,
        public readonly PaginationConfig $pagination,
    ) {}

    /** Get column names suitable for a SELECT query */
    public function getSelectExpressions(): array
    {
        return array_map(function (ColumnDefinition $col) {
            if ($col->expression) {
                return "{$col->expression} as {$col->name}";
            }
            return $col->name;
        }, $this->columns);
    }

    /** Get columns marked as searchable */
    public function getSearchableColumns(): array
    {
        return array_filter($this->columns, fn(ColumnDefinition $col) => $col->searchable);
    }

    /** Find a column by name */
    public function getColumn(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $col) {
            if ($col->name === $name) return $col;
        }
        return null;
    }
}
```

---

## Phase 2: Fluent Schema Builder

The builder provides the ergonomic API. It collects configuration and produces an immutable `TableSchema`.

```php
namespace Echo\Framework\Admin\Schema;

class TableSchemaBuilder
{
    private string $table;
    private string $primaryKey = 'id';
    private array $columns = [];      // TableColumnBuilder[]
    private array $filters = [];      // TableFilterBuilder[]
    private array $filterLinks = [];  // ['label' => 'condition']
    private array $actions = [];      // ['name' => 'label']
    private array $joins = [];
    private string $defaultOrderBy = 'id';
    private string $defaultSort = 'DESC';
    private string $dateColumn = 'created_at';
    private int $perPage = 10;

    public function __construct(string $table)
    {
        $this->table = $table;
    }

    public function primaryKey(string $key): self
    {
        $this->primaryKey = $key;
        return $this;
    }

    /**
     * Define a column.
     *
     * Usage:
     *   $builder->column('email', 'Email')
     *   $builder->column('name', 'Name', 'CONCAT(first_name, " ", surname)')
     */
    public function column(string $name, ?string $label = null, ?string $expression = null): TableColumnBuilder
    {
        $colBuilder = new TableColumnBuilder($name, $label, $expression);
        $this->columns[] = $colBuilder;
        return $colBuilder;
    }

    public function join(string $sql): self
    {
        $this->joins[] = $sql;
        return $this;
    }

    public function filter(string $name, string $column): TableFilterBuilder
    {
        $filterBuilder = new TableFilterBuilder($name, $column);
        $this->filters[] = $filterBuilder;
        return $filterBuilder;
    }

    public function filterLink(string $label, string $condition): self
    {
        $this->filterLinks[] = ['label' => $label, 'condition' => $condition];
        return $this;
    }

    public function bulkAction(string $name, string $label): self
    {
        $this->actions[] = ['name' => $name, 'label' => $label];
        return $this;
    }

    public function defaultSort(string $column, string $direction = 'DESC'): self
    {
        $this->defaultOrderBy = $column;
        $this->defaultSort = strtoupper($direction);
        return $this;
    }

    public function dateColumn(string $column): self
    {
        $this->dateColumn = $column;
        return $this;
    }

    public function perPage(int $count): self
    {
        $this->perPage = $count;
        return $this;
    }

    /**
     * Build the immutable TableSchema.
     */
    public function build(): TableSchema
    {
        return new TableSchema(
            table: $this->table,
            primaryKey: $this->primaryKey,
            columns: array_map(fn(TableColumnBuilder $c) => $c->build(), $this->columns),
            filters: array_map(fn(TableFilterBuilder $f) => $f->build(), $this->filters),
            filterLinks: array_map(
                fn(array $fl) => new FilterLinkDefinition($fl['label'], $fl['condition']),
                $this->filterLinks
            ),
            actions: array_map(
                fn(array $a) => new ActionDefinition($a['name'], $a['label']),
                $this->actions
            ),
            joins: $this->joins,
            defaultOrderBy: $this->defaultOrderBy,
            defaultSort: $this->defaultSort,
            dateColumn: $this->dateColumn,
            pagination: new PaginationConfig(perPage: $this->perPage),
        );
    }
}
```

### `TableColumnBuilder`

```php
namespace Echo\Framework\Admin\Schema;

class TableColumnBuilder
{
    private bool $sortable = false;
    private bool $searchable = false;
    private ?string $format = null;
    private ?\Closure $formatter = null;
    private ?string $cellTemplate = null;

    public function __construct(
        private string $name,
        private ?string $label = null,
        private ?string $expression = null,
    ) {
        $this->label = $label ?? ucfirst(str_replace('_', ' ', $name));
    }

    public function sortable(): self { $this->sortable = true; return $this; }
    public function searchable(): self { $this->searchable = true; return $this; }

    /** Named format: 'date', 'check', 'badge' */
    public function format(string $format): self { $this->format = $format; return $this; }

    /** Custom formatter closure: fn(string $column, mixed $value): string */
    public function formatUsing(\Closure $formatter): self { $this->formatter = $formatter; return $this; }

    /** Override the cell template for this column */
    public function cellTemplate(string $template): self { $this->cellTemplate = $template; return $this; }

    public function build(): ColumnDefinition
    {
        return new ColumnDefinition(
            name: $this->name,
            label: $this->label,
            expression: $this->expression,
            sortable: $this->sortable,
            searchable: $this->searchable,
            format: $this->format,
            formatter: $this->formatter,
            cellTemplate: $this->cellTemplate,
        );
    }
}
```

### `TableFilterBuilder`

```php
namespace Echo\Framework\Admin\Schema;

class TableFilterBuilder
{
    private string $label;
    private string $type = 'dropdown';
    private array $options = [];
    private ?string $optionsQuery = null;

    public function __construct(
        private string $name,
        private string $column,
    ) {
        $this->label = ucfirst(str_replace('_', ' ', $name));
    }

    public function label(string $label): self { $this->label = $label; return $this; }
    public function type(string $type): self { $this->type = $type; return $this; }

    /** Static options: [['value' => 'admin', 'label' => 'Admin'], ...] */
    public function options(array $options): self { $this->options = $options; return $this; }

    /** Dynamic options from SQL: "SELECT id as value, name as label FROM ..." */
    public function optionsFrom(string $query): self { $this->optionsQuery = $query; return $this; }

    public function build(): FilterDefinition
    {
        return new FilterDefinition(
            name: $this->name,
            column: $this->column,
            label: $this->label,
            type: $this->type,
            options: $this->options,
            optionsQuery: $this->optionsQuery,
        );
    }
}
```

---

## Phase 3: Data Source Abstraction

Extracts data fetching from the controller. The default implementation uses `QueryBuilder` (same as today), but the interface allows swapping in custom sources.

```php
namespace Echo\Framework\Admin;

use Echo\Framework\Admin\Schema\TableSchema;

interface TableDataSource
{
    /**
     * Fetch paginated rows.
     *
     * @return TableResult Contains rows, total count, and pagination metadata
     */
    public function fetch(
        TableSchema $schema,
        int $page,
        int $perPage,
        string $orderBy,
        string $sort,
        array $whereConditions,  // SQL WHERE clauses
        array $whereParams,      // Bound parameters
    ): TableResult;
}
```

### `TableResult`

```php
namespace Echo\Framework\Admin;

final class TableResult
{
    public function __construct(
        public readonly array $rows,         // Fetched row data
        public readonly int $totalRows,      // Total matching rows (before pagination)
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $totalPages,
    ) {}
}
```

### `QueryBuilderDataSource` (default)

```php
namespace Echo\Framework\Admin;

use Echo\Framework\Admin\Schema\TableSchema;

class QueryBuilderDataSource implements TableDataSource
{
    public function fetch(
        TableSchema $schema,
        int $page,
        int $perPage,
        string $orderBy,
        string $sort,
        array $whereConditions,
        array $whereParams,
    ): TableResult {
        $from = $schema->table . ' ' . implode(' ', $schema->joins);
        $select = $schema->getSelectExpressions();

        // Always include primary key
        $pkIncluded = false;
        foreach ($select as $expr) {
            if ($expr === $schema->primaryKey || str_ends_with($expr, " as {$schema->primaryKey}")) {
                $pkIncluded = true;
                break;
            }
        }
        if (!$pkIncluded) {
            array_unshift($select, $schema->primaryKey);
        }

        // Count total
        $totalRows = qb()->select(['COUNT(*) as cnt'])
            ->from($from)
            ->where($whereConditions)
            ->params($whereParams)
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;

        // Fetch page
        $offset = $perPage * ($page - 1);
        $rows = qb()->select($select)
            ->from($from)
            ->where($whereConditions)
            ->params($whereParams)
            ->orderBy(["$orderBy $sort"])
            ->limit($perPage)
            ->offset($offset)
            ->execute()
            ->fetchAll();

        return new TableResult(
            rows: $rows ?: [],
            totalRows: (int) $totalRows,
            page: $page,
            perPage: $perPage,
            totalPages: (int) ceil($totalRows / $perPage),
        );
    }
}
```

---

## Phase 4: Module State

Extracts session management from the controller into a focused class.

```php
namespace Echo\Framework\Admin;

class ModuleState
{
    public function __construct(private string $moduleKey) {}

    public function getPage(): int { return $this->get('page', 1); }
    public function setPage(int $page): void { $this->set('page', $page); }

    public function getPerPage(int $default): int { return $this->get('per_page', $default); }
    public function setPerPage(int $count): void { $this->set('per_page', $count); }

    public function getOrderBy(string $default): string { return $this->get('order_by', $default); }
    public function setOrderBy(string $col): void { $this->set('order_by', $col); }

    public function getSort(string $default): string { return $this->get('sort', $default); }
    public function setSort(string $dir): void { $this->set('sort', $dir); }

    public function getActiveFilterLink(): int { return $this->get('filter_link', 0); }
    public function setActiveFilterLink(int $idx): void { $this->set('filter_link', $idx); }

    public function getFilter(string $key): mixed { return $this->getFilters()[$key] ?? null; }
    public function setFilter(string $key, mixed $value): void
    {
        $filters = $this->getFilters();
        $filters[$key] = $value;
        $this->set('filters', $filters);
    }
    public function removeFilter(string $key): void
    {
        $filters = $this->getFilters();
        unset($filters[$key]);
        $this->set('filters', $filters);
    }
    public function getFilters(): array { return $this->get('filters', []); }
    public function clearFilters(): void { $this->set('filters', []); $this->setPage(1); }
    public function hasFilters(): bool { return !empty($this->getFilters()); }

    private function get(string $key, mixed $default): mixed
    {
        $data = session()->get($this->moduleKey);
        return $data[$key] ?? $default;
    }

    private function set(string $key, mixed $value): void
    {
        $data = session()->get($this->moduleKey) ?? [];
        $data[$key] = $value;
        session()->set($this->moduleKey, $data);
    }
}
```

---

## Phase 5: ModuleController

Replaces `AdminController`. Composes the schema, data source, state, and rendering. Substantially smaller because each concern is handled by its own component.

```php
namespace Echo\Framework\Http;

use Echo\Framework\Admin\{ModuleState, QueryBuilderDataSource, TableDataSource, TableResult};
use Echo\Framework\Admin\Schema\{TableSchema, TableSchemaBuilder, ColumnDefinition, FilterDefinition};
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Session\Flash;

#[Group(path_prefix: "/admin", middleware: ["auth"])]
abstract class ModuleController extends Controller
{
    protected TableSchema $tableSchema;
    protected ModuleState $state;
    protected TableDataSource $dataSource;

    // Permissions (same as current)
    protected bool $has_edit = true;
    protected bool $has_create = true;
    protected bool $has_delete = true;
    protected bool $has_export = true;
    protected bool $has_show = true;

    // Form config (kept as-is until Phase 2 FormSchema)
    protected array $form_columns = [];
    protected array $form_controls = [];
    protected array $form_dropdowns = [];
    protected array $form_datalist = [];
    protected array $form_readonly = [];
    protected array $form_disabled = [];
    protected array $form_defaults = [];
    protected array $file_accept = [];
    protected array $validation_rules = [];

    abstract protected function defineTable(TableSchemaBuilder $builder): void;

    public function __construct(?string $tableName = null)
    {
        // Build schema
        $builder = new TableSchemaBuilder($tableName);
        $this->defineTable($builder);
        $this->tableSchema = $builder->build();

        // Initialize components
        $this->state = new ModuleState($this->getModuleLink());
        $this->dataSource = new QueryBuilderDataSource();

        $this->init();
    }

    // --- Routes (same URLs and HTMX targets as current) ---

    #[Get("/", "admin.index")]
    public function index(): string
    {
        return $this->renderModule([
            ...$this->getCommonData(),
            'content' => $this->renderTable(),
        ]);
    }

    #[Get("/page/{page}", "admin.page")]
    public function page(int $page): string
    {
        $this->state->setPage($page);
        return $this->index();
    }

    #[Get("/sort/{idx}", "admin.sort")]
    public function sort(int $idx): string
    {
        $columns = $this->tableSchema->columns;
        if (!isset($columns[$idx])) return $this->index();

        $column = $columns[$idx]->name;
        $currentOrderBy = $this->state->getOrderBy($this->tableSchema->defaultOrderBy);
        $currentSort = $this->state->getSort($this->tableSchema->defaultSort);

        if ($currentOrderBy === $column) {
            $this->state->setSort($currentSort === 'ASC' ? 'DESC' : 'ASC');
        } else {
            $this->state->setOrderBy($column);
            $this->state->setSort('DESC');
        }
        return $this->index();
    }

    // ... remaining route handlers follow the same pattern as current AdminController
    // but delegate to $this->state instead of direct session access
    // and delegate to $this->dataSource instead of inline query building

    // --- Rendering ---

    protected function renderTable(): string
    {
        $result = $this->fetchTableData();

        // Apply formatters
        $rows = array_map(fn(array $row) => $this->formatRow($row), $result->rows);

        return $this->render('admin/table.html.twig', [
            ...$this->getCommonData(),
            'headers' => $this->buildHeaders(),
            'table_actions' => $this->buildTableActions(),
            'filters' => $this->buildFilterData(),
            'data' => ['rows' => $rows],
            'caption' => $this->buildCaption($result),
            'pagination' => [
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total_pages' => $result->totalPages,
                'total_rows' => $result->totalRows,
                'links' => $this->tableSchema->pagination->paginationLinks,
            ],
        ]);
    }

    private function fetchTableData(): TableResult
    {
        [$where, $params] = $this->buildWhereConditions();

        return $this->dataSource->fetch(
            schema: $this->tableSchema,
            page: $this->state->getPage(),
            perPage: $this->state->getPerPage($this->tableSchema->pagination->perPage),
            orderBy: $this->state->getOrderBy($this->tableSchema->defaultOrderBy),
            sort: $this->state->getSort($this->tableSchema->defaultSort),
            whereConditions: $where,
            whereParams: $params,
        );
    }

    private function formatRow(array $row): array
    {
        $row = $this->tableOverride($row);
        foreach ($this->tableSchema->columns as $col) {
            if (!array_key_exists($col->name, $row)) continue;
            $value = $row[$col->name];

            if ($col->formatter) {
                $row[$col->name] = ($col->formatter)($col->name, $value);
            } elseif ($col->format) {
                $row[$col->name] = $this->applyNamedFormat($col->format, $col->name, $value);
            }
        }
        return $row;
    }

    // Hook for subclass row customization (same as current)
    protected function tableOverride(array $row): array { return $row; }
}
```

---

## Migration Example: UsersController

### Before (v0.2.x)

```php
class UsersController extends AdminController
{
    public function __construct()
    {
        $this->table_columns = [
            "ID" => "id",
            "UUID" => "uuid",
            "Role" => "role",
            "Name" => "CONCAT(first_name, ' ', surname) as name",
            "Email" => "email",
            "Created" => "created_at",
        ];
        $this->search_columns = ["Email"];
        $this->filter_dropdowns = [
            "role" => [
                ["value" => "standard", "label" => "Standard"],
                ["value" => "admin", "label" => "Admin"],
            ]
        ];
        // ... 50+ more lines of array config ...
        parent::__construct("users");
    }
}
```

### After (v0.3.0)

```php
class UsersController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC')
                ->perPage(10);

        $builder->column('id', 'ID')->sortable();
        $builder->column('uuid', 'UUID');
        $builder->column('role', 'Role')->sortable();
        $builder->column('name', 'Name', 'CONCAT(first_name, " ", surname)')
                ->sortable()
                ->searchable();
        $builder->column('email', 'Email')
                ->sortable()
                ->searchable();
        $builder->column('created_at', 'Created')
                ->sortable()
                ->format('date');

        $builder->filter('role', 'role')
                ->label('Role')
                ->options([
                    ['value' => 'standard', 'label' => 'Standard'],
                    ['value' => 'admin', 'label' => 'Admin'],
                ]);
    }

    // Form config stays as-is until FormSchema phase
    public function __construct()
    {
        $this->form_columns = [/* ... unchanged ... */];
        $this->form_controls = [/* ... unchanged ... */];
        $this->validation_rules = [/* ... unchanged ... */];
        parent::__construct('users');
    }
}
```

### Migration Example: AuditController (Complex)

Demonstrates joins, filter links, custom formatters, and dynamic filter options.

```php
class AuditController extends ModuleController
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->primaryKey('audits.id')
                ->join('LEFT JOIN users ON users.id = audits.user_id')
                ->dateColumn('audits.created_at')
                ->defaultSort('audits.id', 'DESC');

        $builder->column('id', 'ID', 'audits.id')->sortable();
        $builder->column('user_name', 'User', "COALESCE(CONCAT(users.first_name, ' ', users.surname), 'System')");
        $builder->column('auditable_type', 'Type')->searchable();
        $builder->column('auditable_id', 'Record ID');
        $builder->column('event', 'Event')
                ->sortable()
                ->formatUsing(fn($col, $val) => $this->formatEvent($val));
        $builder->column('ip_address', 'IP', 'audits.ip_address')->searchable();
        $builder->column('created_at', 'Created', 'audits.created_at')
                ->sortable()
                ->format('date');

        $builder->filter('event', 'audits.event')
                ->label('Event')
                ->options([
                    ['value' => 'created', 'label' => 'Created'],
                    ['value' => 'updated', 'label' => 'Updated'],
                    ['value' => 'deleted', 'label' => 'Deleted'],
                ]);

        $builder->filter('user', 'audits.user_id')
                ->label('User')
                ->optionsFrom("SELECT id as value, CONCAT(first_name, ' ', surname) as label FROM users ORDER BY label");

        $builder->filterLink('Created', "audits.event = 'created'");
        $builder->filterLink('Updated', "audits.event = 'updated'");
        $builder->filterLink('Deleted', "audits.event = 'deleted'");
    }
}
```

---

## File Structure

New files to create:

```
src/Framework/Admin/Schema/
├── ColumnDefinition.php
├── FilterDefinition.php
├── FilterLinkDefinition.php
├── ActionDefinition.php
├── PaginationConfig.php
├── TableSchema.php
├── TableSchemaBuilder.php
├── TableColumnBuilder.php
└── TableFilterBuilder.php

src/Framework/Admin/
├── TableDataSource.php       (interface)
├── QueryBuilderDataSource.php
├── TableResult.php
└── ModuleState.php

src/Framework/Http/
└── ModuleController.php      (new abstract controller)
```

Existing files to modify:

```
templates/admin/table.html.twig  — minor: adapt to receive ColumnDefinition metadata
templates/admin/filter.html.twig — no change expected
```

Files that remain unchanged until all modules are migrated:

```
src/Framework/Http/AdminController.php  — kept as-is for backwards compatibility
```

---

## Implementation Order

| Step | What | Depends On | Notes |
|------|------|------------|-------|
| 1 | Value objects (`Schema/` directory) | Nothing | Pure data classes, no side effects. Write tests. |
| 2 | `TableSchemaBuilder` + sub-builders | Step 1 | Builder → value object pipeline. Write tests. |
| 3 | `ModuleState` | Nothing | Extract from AdminController session methods. Write tests. |
| 4 | `TableDataSource` interface + `QueryBuilderDataSource` | Step 1 | Extract from AdminController `runTableQuery()`. Write tests. |
| 5 | `TableResult` | Nothing | Simple value object. |
| 6 | `ModuleController` | Steps 1–5 | Wire it all together. Keep form handling as-is for now. |
| 7 | Migrate `UsersController` | Step 6 | First migration — validates the whole pipeline works. |
| 8 | Migrate remaining controllers | Step 7 | `ActivityController`, `AuditController`, etc. |
| 9 | Template refinements | Step 7 | Add `cellTemplate` support, formatter rendering. |
| 10 | Remove `AdminController` | Step 8 | Only after all modules are migrated. |

---

## What's Explicitly Out of Scope for v0.3.0

- **FormSchema** — forms stay array-configured. They're less painful than tables and can wait.
- **Widget integration** — admin tables are NOT dashboard widgets. They serve different purposes. Dashboard widgets display summaries; admin modules provide full CRUD interfaces.
- **Custom DataSource implementations** — the `TableDataSource` interface exists for extensibility, but only `QueryBuilderDataSource` ships in v0.3.0.
- **Row-level template overrides** — `cellTemplate` per-column is in scope; full row template overrides are deferred (adds complexity with minimal gain given the HTMX structure).

---

## Design Decisions & Rationale

**Why value objects instead of arrays?**
Arrays silently accept wrong keys. `ColumnDefinition` with `readonly` properties fails loudly if misused, and IDEs autocomplete every field.

**Why keep raw SQL in join/expression strings?**
Echo's QueryBuilder operates on raw SQL strings. Wrapping these in an abstraction would add complexity without value — the SQL is the source of truth for these queries.

**Why not make tables into Widgets?**
The existing Widget system (`Widget` → `WidgetRegistry` → dashboard) is designed for self-contained rendering units. Admin modules need routing, CRUD, session state, permissions, and forms — concerns that don't belong in a Widget. Keeping them separate respects single responsibility.

**Why keep `tableOverride()` and similar hooks?**
The hook pattern works well for the AuditController-style customizations (custom `show()`, `hasDelete()` overrides). The new system preserves these escape hatches so modules that need custom behavior aren't forced into contortions.

**Why a separate `ModuleController` instead of refactoring `AdminController` in-place?**
Gradual migration. Both can coexist. Controllers migrate one at a time. If something breaks, the old controller is still there. Once migration is complete, `AdminController` is deleted.
