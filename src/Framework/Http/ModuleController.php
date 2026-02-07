<?php

namespace Echo\Framework\Http;

use App\Models\FileInfo;
use App\Services\Admin\SidebarService;
use Echo\Framework\Admin\{ModuleState, QueryBuilderDataSource, TableDataSource, TableResult};
use Echo\Framework\Admin\Schema\{ColumnDefinition, TableSchema, TableSchemaBuilder};
use Echo\Framework\Audit\AuditLogger;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Session\Flash;
use PDOStatement;
use RuntimeException;
use Throwable;
use Twig\TwigFunction;

#[Group(path_prefix: "/admin", middleware: ["auth"])]
abstract class ModuleController extends Controller
{
    // --- Schema-driven components ---
    protected TableSchema $tableSchema;
    protected ModuleState $state;
    protected TableDataSource $dataSource;

    // --- Module metadata (populated from DB) ---
    protected string $module_icon = "";
    protected string $module_link = "";
    protected string $module_title = "";

    // --- Permissions ---
    protected bool $has_edit = true;
    protected bool $has_create = true;
    protected bool $has_delete = true;
    protected bool $has_export = true;
    protected bool $has_show = true;

    // --- Form config (kept as arrays until FormSchema phase) ---
    protected array $form_columns = [];
    protected array $form_controls = [];
    protected array $form_dropdowns = [];
    protected array $form_datalist = [];
    protected array $form_readonly = [];
    protected array $form_disabled = [];
    protected array $form_defaults = [];
    protected array $file_accept = [];
    protected array $validation_rules = [];

    /**
     * Subclasses define their table schema here.
     */
    abstract protected function defineTable(TableSchemaBuilder $builder): void;

    public function __construct(private ?string $table_name = null)
    {
        // Build schema
        $builder = new TableSchemaBuilder($table_name);
        $this->defineTable($builder);
        $this->tableSchema = $builder->build();

        // Initialize components
        $this->dataSource = new QueryBuilderDataSource();
        $this->init();
        $this->state = new ModuleState($this->module_link);
    }

    // =========================================================================
    // Routes — same URLs, same HTMX targets as AdminController
    // =========================================================================

    #[Get("/", "admin.index")]
    public function index(): string
    {
        return $this->renderModule($this->getModuleData());
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
        if (!isset($columns[$idx])) {
            return $this->index();
        }

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

    #[Get("/per-page/{count}", "admin.per-page")]
    public function perPage(int $count): string
    {
        if (in_array($count, $this->tableSchema->pagination->perPageOptions)) {
            $this->state->setPerPage($count);
            $this->state->setPage(1);
        }
        return $this->index();
    }

    #[Get("/export-csv", "admin.export-csv")]
    public function export_csv(): mixed
    {
        if (!$this->hasExport()) {
            return $this->permissionDenied();
        }

        [$where, $params] = $this->buildWhereConditions();
        $from = $this->tableSchema->table . ' ' . implode(' ', $this->tableSchema->joins);
        $select = $this->tableSchema->getSelectExpressions();
        $orderBy = $this->state->getOrderBy($this->tableSchema->defaultOrderBy);
        $sort = $this->state->getSort($this->tableSchema->defaultSort);

        $rows = qb()->select($select)
            ->from($from)
            ->where($where)
            ->params($params)
            ->orderBy(["$orderBy $sort"])
            ->execute()
            ->fetchAll();

        if ($rows) {
            $this->streamCSV($rows, $this->module_link . '_export.csv');
        }
        return null;
    }

    #[Get("/modal/create", "admin.create")]
    public function create(): string
    {
        if (!$this->hasCreate()) {
            return $this->permissionDenied();
        }
        return $this->renderModule($this->getFormData(null, 'create'));
    }

    #[Get("/modal/filter", "admin.render-filter")]
    public function render_filter(): string
    {
        return $this->renderFilter();
    }

    #[Get("/filter/link/{index}", "admin.filter-link")]
    public function filter_link(int $index): string
    {
        $this->state->setActiveFilterLink($index);
        $this->state->setPage(1);
        return $this->index();
    }

    #[Get("/filter/count/{index}", "admin.filter-count")]
    public function filter_count(int $index)
    {
        $filterLinks = $this->tableSchema->filterLinks;
        if (!isset($filterLinks[$index])) {
            return 0;
        }

        [$where, $params] = $this->buildWhereConditions();
        $where[] = $filterLinks[$index]->condition;
        $from = $this->tableSchema->table . ' ' . implode(' ', $this->tableSchema->joins);

        return qb()->select(['COUNT(*) as cnt'])
            ->from($from)
            ->where($where)
            ->params($params)
            ->execute()
            ->fetch(\PDO::FETCH_ASSOC)['cnt'] ?? 0;
    }

    #[Post("/table-action", "admin.table-action")]
    public function table_action(): string
    {
        $this->handleRequest($this->request->request);
        return $this->index();
    }

    #[Post("/modal/filter", "admin.set-filter")]
    public function set_filter(): string
    {
        $clear = isset($this->request->request->filter_clear);
        $valid = $this->validate([
            "filter_search" => [],
            "filter_date_start" => [],
            "filter_date_end" => [],
            "filter_clear" => [],
            "filter_dropdowns" => [],
        ], "filter");

        if ($valid) {
            if ($clear) {
                $this->state->clearFilters();
            } else {
                $this->handleRequest($valid);
            }
            header("HX-Retarget: #module");
            header("HX-Reselect: #module");
            header("HX-Reswap: outerHTML");
            return $this->index();
        }

        Flash::add("warning", "Validation error");
        header("HX-Retarget: .modal-dialog");
        header("HX-Reselect: .modal-content");
        return $this->render_filter();
    }

    #[Get("/modal/{id}", "admin.show")]
    public function show(int $id): string
    {
        if (!$this->hasShow($id)) {
            return $this->permissionDenied();
        }
        return $this->renderModule($this->getFormData($id, 'show'));
    }

    #[Get("/modal/{id}/edit", "admin.edit")]
    public function edit(int $id): string
    {
        if (!$this->hasEdit($id)) {
            return $this->permissionDenied();
        }
        return $this->renderModule($this->getFormData($id, 'edit'));
    }

    #[Post("/", "admin.store")]
    public function store(): string
    {
        if (!$this->hasCreate()) {
            return $this->permissionDenied();
        }
        $valid = $this->validate($this->validation_rules);
        if ($valid) {
            $request = $this->massageRequest(null, (array) $valid);
            $id = $this->handleStore($request);
            if ($id) {
                Flash::add("success", "Successfully created record");
                header("HX-Retarget: #module");
                header("HX-Reselect: #module");
                header("HX-Reswap: outerHTML");
                return $this->index();
            }
        }

        Flash::add("warning", "Validation error");
        header("HX-Retarget: .modal-dialog");
        header("HX-Reselect: .modal-content");
        return $this->create();
    }

    #[Post("/{id}/update", "admin.update")]
    public function update(int $id): string
    {
        if (!$this->hasEdit($id)) {
            return $this->permissionDenied();
        }
        $valid = $this->validate($this->validation_rules, $id);
        if ($valid) {
            $request = $this->massageRequest($id, (array) $valid);
            $result = $this->handleUpdate($id, $request);
            if ($result) {
                Flash::add("success", "Successfully updated record");
                header("HX-Retarget: #module");
                header("HX-Reselect: #module");
                header("HX-Reswap: outerHTML");
                return $this->index();
            }
        }

        Flash::add("warning", "Validation error");
        header("HX-Retarget: .modal-dialog");
        header("HX-Reselect: .modal-content");
        return $this->edit($id);
    }

    #[Post("/{id}/destroy", "admin.destroy")]
    public function destroy(int $id): string
    {
        if (!$this->hasDelete($id)) {
            return $this->permissionDenied();
        }
        $result = $this->handleDestroy($id);
        if ($result) {
            Flash::add("success", "Successfully deleted record");
        }
        header("HX-Retarget: #module");
        header("HX-Reselect: #module");
        header("HX-Reswap: outerHTML");
        return $this->index();
    }

    // =========================================================================
    // Table rendering (schema-driven)
    // =========================================================================

    protected function renderTable(): string
    {
        if (empty($this->tableSchema->columns)) {
            return '';
        }

        $result = $this->fetchTableData();

        // Apply formatters to each row
        $rows = array_map(fn(array $row) => $this->formatRow($row), $result->rows);

        // Build headers: column alias => label
        $headers = [];
        foreach ($this->tableSchema->columns as $col) {
            $headers[$col->name] = $col->label;
        }

        // Build table actions
        $tableActions = [];
        foreach ($this->tableSchema->actions as $action) {
            $tableActions[] = ['value' => $action->name, 'label' => $action->label];
        }
        if ($this->has_delete) {
            $tableActions[] = ['value' => 'delete', 'label' => 'Delete'];
        }

        // Register Twig functions
        $this->registerFunctions();

        // Build caption
        $start = 1 + ($result->page * $result->perPage) - $result->perPage;
        $end = min($result->page * $result->perPage, $result->totalRows);

        $orderBy = $this->state->getOrderBy($this->tableSchema->defaultOrderBy);
        $sort = $this->state->getSort($this->tableSchema->defaultSort);

        return $this->render("admin/table.html.twig", [
            ...$this->getCommonData(),
            "headers" => $headers,
            "has_delete" => $this->has_delete,
            "has_edit" => $this->has_edit,
            "has_create" => $this->has_create,
            "table_actions" => $tableActions,
            "order_by" => $orderBy,
            "filters" => [
                "show" => !empty($this->tableSchema->getSearchableColumns())
                    || $this->tableSchema->dateColumn !== ''
                    || !empty($this->tableSchema->filters),
                "show_clear" => $this->state->hasFilters(),
                "filter_links" => [
                    "show" => !empty($this->tableSchema->filterLinks),
                    "active" => $this->state->getActiveFilterLink(),
                    "links" => array_map(fn($fl) => $fl->label, $this->tableSchema->filterLinks),
                ],
                "order_by" => $orderBy,
                "sort" => $sort,
            ],
            "caption" => $result->totalPages > 1
                ? "Showing {$start}–{$end} of {$result->totalRows} results"
                : "",
            "data" => [
                "rows" => $rows,
            ],
            "pagination" => [
                "page" => $result->page,
                "per_page" => $result->perPage,
                "total_pages" => $result->totalPages,
                "total_rows" => $result->totalRows,
                "links" => $this->tableSchema->pagination->paginationLinks,
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

    // =========================================================================
    // Filter / WHERE building
    // =========================================================================

    /**
     * Build WHERE conditions from active filters, search, date range, etc.
     *
     * @return array{0: string[], 1: mixed[]} [$conditions, $params]
     */
    protected function buildWhereConditions(): array
    {
        $where = [];
        $params = [];

        // Filter links
        $filterLinks = $this->tableSchema->filterLinks;
        if (!empty($filterLinks)) {
            $activeIdx = $this->state->getActiveFilterLink();
            if (isset($filterLinks[$activeIdx])) {
                $where[] = $filterLinks[$activeIdx]->condition;
            }
        }

        // Search filter
        $searchTerm = $this->state->getFilter('search');
        if ($searchTerm) {
            $searchClauses = [];
            foreach ($this->tableSchema->getSearchableColumns() as $col) {
                $expr = $col->expression ?? $col->name;
                $searchClauses[] = "($expr LIKE ?)";
                $params[] = "%{$searchTerm}%";
            }
            if (!empty($searchClauses)) {
                $where[] = '(' . implode(' OR ', $searchClauses) . ')';
            }
        }

        // Date range filter
        $dateCol = $this->tableSchema->dateColumn;
        $dateStart = $this->state->getFilter('date_start');
        $dateEnd = $this->state->getFilter('date_end');
        if ($dateCol) {
            if ($dateStart && $dateEnd) {
                $where[] = "$dateCol BETWEEN ? AND ?";
                $params[] = $dateStart;
                $params[] = $dateEnd;
            } elseif ($dateStart) {
                $where[] = "$dateCol >= ?";
                $params[] = $dateStart;
            } elseif ($dateEnd) {
                $where[] = "$dateCol <= ?";
                $params[] = $dateEnd;
            }
        }

        // Dropdown filters
        foreach ($this->tableSchema->filters as $i => $filter) {
            $selected = $this->state->getFilter("dropdowns_" . $i);
            if ($selected) {
                $where[] = "{$filter->column} = ?";
                $params[] = $selected;
            }
        }

        return [$where, $params];
    }

    // =========================================================================
    // Row formatting
    // =========================================================================

    private function formatRow(array $row): array
    {
        $row = $this->tableOverride($row);
        return $row;
    }

    /**
     * Format a single cell value using the schema's format/formatter.
     * Called from the Twig `format()` function.
     */
    private function formatValue(string $column, mixed $value): mixed
    {
        $col = $this->tableSchema->getColumn($column);
        if (!$col) {
            return $value;
        }

        if ($col->formatter) {
            return ($col->formatter)($column, $value);
        }

        if ($col->format) {
            return $this->applyNamedFormat($col->format, $column, $value);
        }

        return $value;
    }

    private function applyNamedFormat(string $format, string $column, mixed $value): mixed
    {
        return match ($format) {
            'check' => $this->render("admin/format/check.html.twig", [
                'id' => $column,
                'title' => $this->tableSchema->getColumn($column)?->label ?? $column,
                'value' => $value,
            ]),
            default => $value,
        };
    }

    /**
     * Hook for subclass row customization.
     */
    protected function tableOverride(array $row): array
    {
        return $row;
    }

    // =========================================================================
    // Filter rendering
    // =========================================================================

    protected function renderFilter(): string
    {
        if (empty($this->tableSchema->columns)) {
            return '';
        }

        $searchTerm = $this->state->getFilter('search') ?? '';
        $dateStart = $this->state->getFilter('date_start') ?? '';
        $dateEnd = $this->state->getFilter('date_end') ?? '';

        // Build dropdown filter data
        $dropdownFilters = [];
        foreach ($this->tableSchema->filters as $i => $filter) {
            $dropdownFilters[] = [
                'column' => $filter->column,
                'label' => $filter->label,
                'selected' => $this->state->getFilter("dropdowns_" . $i),
                'options' => $filter->resolveOptions(),
            ];
        }

        // Determine if date column exists in schema
        $hasDateColumn = $this->tableSchema->dateColumn !== '';

        return $this->render("admin/filter.html.twig", [
            "post" => "/admin/{$this->module_link}/modal/filter",
            "show_clear" => $this->state->hasFilters(),
            "dropdowns" => [
                "show" => !empty($this->tableSchema->filters),
                "filters" => $dropdownFilters,
            ],
            "date_filter" => [
                "show" => $hasDateColumn,
                "start" => $dateStart,
                "end" => $dateEnd,
            ],
            "search" => [
                "show" => !empty($this->tableSchema->getSearchableColumns()),
                "term" => $searchTerm,
            ],
        ]);
    }

    // =========================================================================
    // Form rendering (kept as-is from AdminController)
    // =========================================================================

    protected function renderForm(?int $id, string $type): string
    {
        if (empty($this->form_columns) || !$this->table_name) {
            return '';
        }

        $data = $this->runFormQuery($id);

        if ($type === "edit") {
            $data = $data->fetch();
            $data = $this->formOverride($id, $data);
            $title = "Edit $id";
            $submit = "Save Changes";
        } elseif ($type === "show") {
            $submit = false;
            $data = $data->fetch();
            $title = "View $id";
            foreach ($data as $column => $value) {
                if (!in_array($column, $this->form_readonly)) {
                    $this->form_readonly[] = $column;
                }
            }
        } elseif ($type === "create") {
            $title = "Create";
            $submit = "Create";
        }

        $this->registerFunctions();

        return $this->render("admin/form-modal.html.twig", [
            ...$this->getCommonData(),
            "type" => $type,
            "id" => $id,
            "title" => $title,
            "post" => $id
                ? "/admin/{$this->module_link}/$id/update"
                : "/admin/{$this->module_link}",
            "submit" => $submit,
            "labels" => array_keys($this->form_columns),
            "data" => $data,
        ]);
    }

    private function runFormQuery(?int $id): array|bool|PDOStatement
    {
        if (is_null($id)) {
            $data = [];
            foreach ($this->form_columns as $value) {
                $column = $this->getAlias($value);
                $data[$column] = $this->form_defaults[$column] ?? null;
            }
            return $data;
        }
        $pk = $this->tableSchema->primaryKey;
        return qb()->select(array_values($this->form_columns))
            ->from($this->table_name)
            ->where(["$pk = ?"], $id)
            ->execute();
    }

    protected function formOverride(?int $id, array $form): array
    {
        return $form;
    }

    // =========================================================================
    // Twig function registration
    // =========================================================================

    private function registerFunctions(): void
    {
        $twig = twig();
        $twig->addFunction(new TwigFunction("has_export", fn() => $this->hasExport()));
        $twig->addFunction(new TwigFunction("has_create", fn() => $this->hasCreate()));
        $twig->addFunction(new TwigFunction("has_edit", fn(int $id) => $this->hasEdit($id)));
        $twig->addFunction(new TwigFunction("has_show", fn(int $id) => $this->hasShow($id)));
        $twig->addFunction(new TwigFunction("has_delete", fn(int $id) => $this->hasDelete($id)));
        $twig->addFunction(new TwigFunction("has_row_actions", fn() => $this->has_show || $this->has_edit || $this->has_delete));
        $twig->addFunction(new TwigFunction("control", fn(string $column, ?string $value) => $this->control($column, $value)));
        $twig->addFunction(new TwigFunction("format", fn(string $column, ?string $value) => $this->formatValue($column, $value)));
    }

    // =========================================================================
    // Form controls (kept as-is from AdminController)
    // =========================================================================

    private function control(string $column, ?string $value)
    {
        if (isset($this->form_controls[$column])) {
            $control = $this->form_controls[$column];
            if (in_array($control, ["file", "image"])) {
                $fi = new FileInfo($value);
            }
            return match ($control) {
                "input" => $this->renderControl("input", $column, $value),
                "number" => $this->renderControl("input", $column, $value, [
                    "type" => "number",
                ]),
                "checkbox" => $this->renderControl("input", $column, $value, [
                    "value" => 1,
                    "type" => "checkbox",
                    "class" => "form-check-input ms-1",
                    "checked" => $value != false,
                ]),
                "email" => $this->renderControl("input", $column, $value, [
                    "type" => "email",
                    "autocomplete" => "email",
                ]),
                "password" => $this->renderControl("input", $column, $value, [
                    "type" => "password",
                    "autocomplete" => "current-password",
                ]),
                "dropdown" => $this->renderControl("dropdown", $column, $value, [
                    "class" => "form-select",
                    "options" => key_exists($column, $this->form_dropdowns) && is_string($this->form_dropdowns[$column])
                        ? db()->fetchAll($this->form_dropdowns[$column])
                        : $this->form_dropdowns[$column] ?? '',
                ]),
                "image" => $this->renderControl("image", $column, $value, [
                    "type" => "file",
                    "file" => $fi ? $fi->getAttributes() : false,
                    "stored_name" => $fi ? $fi->stored_name : false,
                    "accept" => $this->file_accept[$column] ?? "image/*",
                ]),
                "file" => $this->renderControl("file", $column, $value, [
                    "type" => "file",
                    "file" => $fi ? $fi->getAttributes() : false,
                    "accept" => $this->file_accept[$column] ?? '',
                ]),
                default => is_callable($control) ? $control($column, $value) : $value,
            };
        }
        return $value;
    }

    private function renderControl(string $type, string $column, ?string $value, array $data = [])
    {
        $required = false;
        if (isset($this->validation_rules[$column])) {
            $required = in_array("required", $this->validation_rules[$column]);
        }
        $default = [
            "type" => "input",
            "class" => "form-control",
            "v_class" => $this->getValidationClass($column, $required),
            "id" => $column,
            "name" => $column,
            "title" => array_search($column, $this->form_columns),
            "value" => $value,
            "placeholder" => "",
            "datalist" => $this->form_datalist[$column] ?? [],
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
            "readonly" => in_array($column, $this->form_readonly),
            "disabled" => in_array($column, $this->form_disabled),
        ];
        $template_data = array_merge($default, $data);
        return $this->render("admin/controls/$type.html.twig", $template_data);
    }

    private function getValidationClass(string $column, bool $required)
    {
        $validation_errors = $this->getValiationErrors();
        $request = $this->request->request;
        $classname = [];
        if (isset($request->$column) || $required && !isset($request->$column)) {
            $classname[] = isset($validation_errors[$column])
                ? 'is-invalid'
                : (isset($request->$column) ? 'is-valid' : '');
        }
        return implode(" ", $classname);
    }

    // =========================================================================
    // Request handling
    // =========================================================================

    private function massageRequest(?int $id, array $request): array
    {
        foreach ($this->form_controls as $column => $control) {
            $value = $request[$column] ?? null;
            if ($value === "NULL") {
                $request[$column] = null;
            }
            if ($control == "checkbox") {
                $request[$column] = $value ? 1 : 0;
            }
            if (in_array($control, ["file", "image"])) {
                $delete_file = $this->request->request->delete_file;
                $is_upload = $this->request->files->$column ?? false;
                if (isset($delete_file[$column])) {
                    $fi = new FileInfo($delete_file[$column]);
                    if ($fi) {
                        $fi->delete();
                        $request[$column] = null;
                    }
                } elseif ($is_upload) {
                    $upload_result = $this->handleFileUpload($is_upload);
                    if ($upload_result) {
                        $request[$column] = $upload_result;
                    }
                } else {
                    unset($request[$column]);
                }
            }
        }
        return $request;
    }

    private function handleRequest(?object $request): void
    {
        if (isset($request->table_action)) {
            $ids = $request->table_selection;
            if ($ids) {
                foreach ($ids as $id) {
                    $this->handleTableAction($id, $request->table_action);
                }
            }
        }
        if (isset($request->filter_date_start) && isset($request->filter_date_end)) {
            $this->state->setFilter("date_start", $request->filter_date_start);
            $this->state->setFilter("date_end", $request->filter_date_end);
            $this->state->setPage(1);
        }
        if (isset($request->filter_search)) {
            $this->state->setFilter("search", $request->filter_search);
            $this->state->setPage(1);
        }
        if (isset($request->filter_dropdowns)) {
            foreach ($request->filter_dropdowns as $i => $value) {
                if ($value !== 'NULL') {
                    $this->state->setFilter("dropdowns_" . $i, $value);
                } else {
                    $this->state->removeFilter("dropdowns_" . $i);
                }
            }
        }
    }

    // =========================================================================
    // CRUD handlers
    // =========================================================================

    protected function handleStore(array $request): mixed
    {
        try {
            $result = qb()->insert($request)
                ->into($this->table_name)
                ->params(array_values($request))
                ->execute();
            if ($result) {
                $id = db()->lastInsertId();
                $newValues = db()->fetch(
                    "SELECT * FROM {$this->table_name} WHERE {$this->tableSchema->primaryKey} = ?",
                    [$id]
                );
                AuditLogger::logCreated($this->table_name, $id, $newValues ?: []);
                return $id;
            }
            return false;
        } catch (Throwable $ex) {
            error_log($ex->getMessage());
            Flash::add("danger", "Create record failed. Check logs.");
            return false;
        }
    }

    protected function handleUpdate(int $id, array $request): bool
    {
        try {
            $pk = $this->tableSchema->primaryKey;
            $oldValues = db()->fetch(
                "SELECT * FROM {$this->table_name} WHERE {$pk} = ?",
                [$id]
            );
            $result = qb()->update($request)
                ->params(array_values($request))
                ->table($this->table_name)
                ->where(["$pk = ?"], $id)
                ->execute();
            if ($result) {
                $newValues = db()->fetch(
                    "SELECT * FROM {$this->table_name} WHERE {$pk} = ?",
                    [$id]
                );
                AuditLogger::logUpdated($this->table_name, $id, $oldValues ?: [], $newValues ?: []);
                return true;
            }
            return false;
        } catch (Throwable $ex) {
            error_log($ex->getMessage());
            Flash::add("danger", "Update record failed. Check logs.");
            return false;
        }
    }

    protected function handleDestroy(int $id): bool
    {
        try {
            $pk = $this->tableSchema->primaryKey;
            $oldValues = db()->fetch(
                "SELECT * FROM {$this->table_name} WHERE {$pk} = ?",
                [$id]
            );
            $result = qb()->delete()
                ->from($this->table_name)
                ->where(["$pk = ?"], $id)
                ->execute();
            if ($result) {
                AuditLogger::logDeleted($this->table_name, $id, $oldValues ?: []);
                return true;
            }
            return false;
        } catch (Throwable $ex) {
            error_log($ex->getMessage());
            Flash::add("danger", "Delete record failed. Check logs.");
            return false;
        }
    }

    protected function handleTableAction(int $id, string $action)
    {
        $exec = match ($action) {
            "delete" => function ($id) {
                if ($this->hasDelete($id)) {
                    $result = $this->handleDestroy($id);
                    if ($result) {
                        Flash::add("success", "Successfully deleted record");
                    }
                } else {
                    Flash::add("warning", "Cannot delete record $id");
                }
            },
            default => fn() => Flash::add("warning", "Unknown action"),
        };
        if (is_callable($exec)) {
            $exec($id);
        }
    }

    // =========================================================================
    // File uploads
    // =========================================================================

    protected function handleFileUpload(array $file): int|false
    {
        $upload_dir = config("paths.uploads");
        if (!is_dir($upload_dir)) {
            $result = mkdir($upload_dir, 0775, true);
            if (!$result) {
                throw new RuntimeException("Cannot create uploads directory" . $file['error']);
            }
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("File upload error: " . $file['error']);
        }

        $og_name = basename($file['name']);
        $extension = pathinfo($og_name, PATHINFO_EXTENSION);
        $unique_name = uniqid('file_', true) . ($extension ? ".$extension" : "");
        $target_path = sprintf("%s/%s", $upload_dir, $unique_name);

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            throw new RuntimeException("Failed to move uploaded file.");
        }

        $mime_type = mime_content_type($target_path);
        $file_size = filesize($target_path);
        $relative_path = sprintf("/uploads/%s", $unique_name);

        $result = FileInfo::create([
            "original_name" => $og_name,
            "stored_name" => $unique_name,
            "path" => $relative_path,
            "mime_type" => $mime_type,
            "size" => $file_size,
        ]);

        return $result->id ?? false;
    }

    // =========================================================================
    // CSV Export
    // =========================================================================

    private function streamCSV(iterable $rows, string $filename = 'export.csv')
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output_handle = fopen('php://output', 'w');
        if ($output_handle === false) {
            throw new RuntimeException("Unable to open output stream");
        }

        // Write headers from schema columns
        $headers = array_map(fn(ColumnDefinition $col) => $col->label, $this->tableSchema->columns);
        fputcsv($output_handle, $headers);

        foreach ($rows as $row) {
            $row = $this->exportOverride($row);
            $ordered_row = [];
            foreach ($this->tableSchema->columns as $col) {
                $ordered_row[] = $this->sanitizeCsvValue($row[$col->name] ?? '');
            }
            fputcsv($output_handle, $ordered_row);
            flush();
        }

        fclose($output_handle);
        exit;
    }

    private function sanitizeCsvValue(mixed $value): string
    {
        $value = (string) $value;
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }

    protected function exportOverride(array $row): array
    {
        return $row;
    }

    // =========================================================================
    // Permissions
    // =========================================================================

    private function hasPermission(): bool
    {
        $module = $this->getModule();
        if (user()->role !== 'admin') {
            $permission = user()->hasPermission($module["id"]);
            if (!$permission) {
                $this->permissionDenied();
            }
        }
        return true;
    }

    private function checkPermission(string $mode): bool
    {
        $module = $this->getModule();
        if (user()->role !== 'admin') {
            return user()->hasModePermission($module["id"], $mode);
        }
        return true;
    }

    protected function hasExport(): bool
    {
        return $this->checkPermission('has_export') && $this->has_export;
    }

    protected function hasCreate(): bool
    {
        return $this->checkPermission('has_create') && $this->has_create && !empty($this->form_columns);
    }

    protected function hasShow(int $id): bool
    {
        return $this->has_show && !empty($this->form_columns);
    }

    protected function hasEdit(int $id): bool
    {
        return $this->checkPermission('has_edit') && $this->has_edit && !empty($this->form_columns);
    }

    protected function hasDelete(int $id): bool
    {
        return $this->checkPermission('has_delete') && $this->has_delete;
    }

    // =========================================================================
    // Validation helpers
    // =========================================================================

    protected function addValidationRule(array $rules, string $field, string $rule): array
    {
        $rules[$field][] = $rule;
        return $rules;
    }

    protected function removeValidationRule(array $rules, string $field, string $remove): array
    {
        if (!isset($rules[$field])) {
            return $rules;
        }

        $rules[$field] = array_filter(
            $rules[$field],
            fn($rule) => explode(':', $rule, 2)[0] !== explode(':', $remove, 2)[0] || $rule !== $remove
        );

        if (empty($rules[$field])) {
            unset($rules[$field]);
        }

        return $rules;
    }

    // =========================================================================
    // Module initialization
    // =========================================================================

    private function getModule(): array|false
    {
        $link = explode('.', request()->getAttribute("route")["name"])[0];
        return db()->fetch("SELECT *
            FROM modules
            WHERE enabled = 1 AND link = ?", [$link]);
    }

    private function getModuleLink(): string
    {
        return $this->module_link;
    }

    private function init(): void
    {
        $this->hasPermission();
        $module = $this->getModule();

        if ($module) {
            $this->module_title = $module['title'];
            $this->module_link = $module['link'];
            $this->module_icon = $module['icon'];
        } else {
            $this->pageNotFound();
        }
    }

    // =========================================================================
    // Rendering helpers
    // =========================================================================

    protected function renderModule(array $data): string
    {
        return $this->render("admin/module.html.twig", $data);
    }

    private function getFormData(?int $id, string $type): array
    {
        return [
            ...$this->getCommonData(),
            "content" => $this->renderForm($id, $type),
        ];
    }

    private function getModuleData(): array
    {
        return [
            ...$this->getCommonData(),
            "content" => $this->renderTable(),
        ];
    }

    protected function getCommonData(): array
    {
        $module = $this->getModule();
        $sidebar_provider = new SidebarService();
        return [
            "sidebar" => [
                "hide" => $sidebar_provider->getState(),
                "links" => $sidebar_provider->getLinks([], [], user()),
            ],
            "user" => [
                "name" => $this->user->first_name . " " . $this->user->surname,
                "email" => $this->user->email,
                "avatar" => $this->user->avatar
                    ? $this->user->avatar()->path
                    : $this->user->gravatar(38),
            ],
            "module" => [
                "link" => $module['link'],
                "title" => $module['title'],
                "icon" => $module['icon'],
            ],
        ];
    }

    // =========================================================================
    // Utility
    // =========================================================================

    private function getAlias(string $str): string
    {
        $str = explode(" as ", $str);
        return end($str);
    }
}
