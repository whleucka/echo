<?php

namespace Echo\Framework\Http;

use App\Models\FileInfo;
use App\Services\Admin\SidebarService;
use Echo\Framework\Admin\{CsvExporter, FormControlRenderer, ModuleState, PivotSyncer, QueryBuilderDataSource, TableDataSource, TableResult};
use Echo\Framework\Admin\Schema\{FormSchema, FormSchemaBuilder, TableSchema, TableSchemaBuilder};
use Echo\Framework\Audit\AuditLogger;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Session\Flash;
use PDOStatement;
use RuntimeException;
use Throwable;
use Twig\TwigFunction;

#[Group(pathPrefix: "/admin", middleware: ["auth"])]
abstract class ModuleController extends Controller
{
    // --- Schema-driven components ---
    protected string $tableName;
    protected TableSchema $tableSchema;
    protected FormSchema $formSchema;
    protected ModuleState $state;
    protected TableDataSource $dataSource;

    // --- Module metadata (populated from DB) ---
    protected string $moduleIcon = "";
    protected string $moduleLink = "";
    protected string $moduleTitle = "";
    private array|false|null $cachedModule = null;

    // --- Current form context ---
    private ?int $currentFormId = null;

    // --- Pivot data to sync after store/update ---
    private array $pendingPivotData = [];

    /**
     * Subclasses define their table schema here.
     * OR they can use NoTableTrait
     */
    abstract protected function defineTable(TableSchemaBuilder $builder): void;

    /**
     * Subclasses define their form schema here.
     * Optional — modules without forms don't need to override this.
     */
    protected function defineForm(FormSchemaBuilder $builder): void {}

    public function __construct()
    {
        if (!isset($this->tableName)) {
            throw new RuntimeException(static::class . " must define a tableName property");
        }

        // Build table schema
        $tableBuilder = new TableSchemaBuilder($this->tableName);
        $this->defineTable($tableBuilder);
        $this->tableSchema = $tableBuilder->build();

        // Build form schema
        $formBuilder = new FormSchemaBuilder();
        $this->defineForm($formBuilder);
        $this->formSchema = $formBuilder->build();

        // Initialize components
        $this->dataSource = new QueryBuilderDataSource();
        $this->init();
        $this->state = new ModuleState($this->moduleLink);
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
    public function exportCsv(): mixed
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
            $exporter = new CsvExporter(
                $this->tableSchema->columns,
                fn(array $row) => $this->exportOverride($row)
            );
            $exporter->stream($rows, $this->moduleLink . '_export.csv');
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
    public function showFilterModal(): string
    {
        return $this->renderFilter();
    }

    #[Get("/filter/link/{index}", "admin.filter-link")]
    public function filterLink(int $index): string
    {
        $this->state->setActiveFilterLink($index);
        $this->state->setPage(1);
        return $this->index();
    }

    #[Get("/filter/count/{index}", "admin.filter-count")]
    public function filterCount(int $index)
    {
        $filterLinks = $this->tableSchema->filterLinks;
        if (!isset($filterLinks[$index])) {
            return 0;
        }

        // Exclude the active filter link from base conditions so each count
        // only applies its own filter link condition (not the active one).
        [$where, $params] = $this->buildWhereConditions(includeFilterLink: false);
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
    public function tableAction(): string
    {
        $this->handleRequest($this->request->request);
        return $this->index();
    }

    #[Post("/modal/filter", "admin.set-filter")]
    public function setFilter(): string
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
        return $this->showFilterModal();
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
        $valid = $this->validate($this->formSchema->getValidationRules());
        if ($valid) {
            $request = $this->massageRequest(null, (array) $valid);
            db()->beginTransaction();
            try {
                $id = $this->handleStore($request);
                if ($id) {
                    (new PivotSyncer())->sync($id, $this->pendingPivotData);
                    $this->pendingPivotData = [];
                    $newValues = db()->fetch(
                        "SELECT * FROM {$this->tableName} WHERE {$this->tableSchema->primaryKey} = ?",
                        [$id]
                    );
                    AuditLogger::logCreated($this->tableName, $id, $newValues ?: []);
                    db()->commit();
                    Flash::add("success", "Successfully created record");
                    header("HX-Retarget: #module");
                    header("HX-Reselect: #module");
                    header("HX-Reswap: outerHTML");
                    return $this->index();
                }
                db()->rollback();
            } catch (Throwable $ex) {
                db()->rollback();
                error_log($ex->getMessage());
                Flash::add("danger", "Create record failed. Check logs.");
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
        $valid = $this->validate($this->formSchema->getValidationRules('edit'), $id);
        if ($valid) {
            $request = $this->massageRequest($id, (array) $valid);
            db()->beginTransaction();
            try {
                $pk = $this->tableSchema->primaryKey;
                $oldValues = db()->fetch(
                    "SELECT * FROM {$this->tableName} WHERE {$pk} = ?",
                    [$id]
                );
                $result = $this->handleUpdate($id, $request);
                if ($result) {
                    (new PivotSyncer())->sync($id, $this->pendingPivotData);
                    $this->pendingPivotData = [];
                    $newValues = db()->fetch(
                        "SELECT * FROM {$this->tableName} WHERE {$pk} = ?",
                        [$id]
                    );
                    AuditLogger::logUpdated($this->tableName, $id, $oldValues ?: [], $newValues ?: []);
                    db()->commit();
                    Flash::add("success", "Successfully updated record");
                    header("HX-Retarget: #module");
                    header("HX-Reselect: #module");
                    header("HX-Reswap: outerHTML");
                    return $this->index();
                }
                db()->rollback();
            } catch (Throwable $ex) {
                db()->rollback();
                error_log($ex->getMessage());
                Flash::add("danger", "Update record failed. Check logs.");
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
        db()->beginTransaction();
        try {
            $pk = $this->tableSchema->primaryKey;
            $oldValues = db()->fetch(
                "SELECT * FROM {$this->tableName} WHERE {$pk} = ?",
                [$id]
            );
            $result = $this->handleDestroy($id);
            if ($result) {
                AuditLogger::logDeleted($this->tableName, $id, $oldValues ?: []);
                db()->commit();
                Flash::add("success", "Successfully deleted record");
            } else {
                db()->rollback();
            }
        } catch (Throwable $ex) {
            db()->rollback();
            error_log($ex->getMessage());
            Flash::add("danger", "Delete record failed. Check logs.");
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

        // Build bulk actions from schema
        $tableActions = [];
        foreach ($this->tableSchema->actions as $action) {
            $tableActions[] = ['value' => $action->name, 'label' => $action->label];
        }

        // Build filtered row actions (respecting requiresForm)
        $rowActions = [];
        foreach ($this->tableSchema->rowActions as $action) {
            if ($action->requiresForm && empty($this->formSchema->fields)) {
                continue;
            }
            $rowActions[] = $action;
        }

        // Build filtered toolbar actions (respecting requiresForm + permissions)
        $toolbarActions = [];
        foreach ($this->tableSchema->toolbarActions as $action) {
            if ($action->requiresForm && empty($this->formSchema->fields)) {
                continue;
            }
            $toolbarActions[] = $action;
        }

        // Register Twig functions
        $this->registerFunctions();

        // Build caption
        $start = 1 + ($result->page * $result->perPage) - $result->perPage;
        $end = min($result->page * $result->perPage, $result->totalRows);

        $orderBy = $this->state->getOrderBy($this->tableSchema->defaultOrderBy);
        $sort = $this->state->getSort($this->tableSchema->defaultSort);
        // Resolve orderBy to column name for template matching (handles expressions like 'activity.id' -> 'id')
        $orderByColumnName = $this->resolveOrderByColumnName($orderBy);

        return $this->render("admin/table.html.twig", [
            ...$this->getCommonData(),
            "headers" => $headers,
            "rowActions" => $rowActions,
            "toolbarActions" => $toolbarActions,
            "tableActions" => $tableActions,
            "orderBy" => $orderByColumnName,
            "filters" => [
                "show" => !empty($this->tableSchema->getSearchableColumns())
                    || $this->tableSchema->dateColumn !== ''
                    || !empty($this->tableSchema->filters),
                "showClear" => $this->state->hasFilters(),
                "filterLinks" => [
                    "show" => !empty($this->tableSchema->filterLinks),
                    "active" => $this->state->getActiveFilterLink(),
                    "links" => array_map(fn($fl) => $fl->label, $this->tableSchema->filterLinks),
                ],
                "orderBy" => $orderByColumnName,
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
                "perPage" => $result->perPage,
                "perPageOptions" => $result->perPageOptions,
                "totalPages" => $result->totalPages,
                "totalRows" => $result->totalRows,
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
            perPageOptions: $this->tableSchema->pagination->perPageOptions,
            orderBy: $this->state->getOrderBy($this->tableSchema->defaultOrderBy),
            sort: $this->state->getSort($this->tableSchema->defaultSort),
            whereConditions: $where,
            whereParams: $params,
        );
    }

    /**
     * Resolve an orderBy value (which may be an expression like 'activity.id')
     * to the corresponding column name for template matching.
     */
    private function resolveOrderByColumnName(string $orderBy): string
    {
        foreach ($this->tableSchema->columns as $col) {
            // Match by name (e.g., 'id') or expression (e.g., 'activity.id')
            if ($col->name === $orderBy || $col->expression === $orderBy) {
                return $col->name;
            }
        }
        return $orderBy;
    }

    // =========================================================================
    // Filter / WHERE building
    // =========================================================================

    /**
     * Build WHERE conditions from active filters, search, date range, etc.
     *
     * @param bool $includeFilterLink Whether to include the active filter link condition (default true).
     *                                Pass false when building counts for individual filter links.
     * @return array{0: string[], 1: mixed[]} [$conditions, $params]
     */
    protected function buildWhereConditions(bool $includeFilterLink = true): array
    {
        $where = [];
        $params = [];

        // Filter links
        if ($includeFilterLink) {
            $filterLinks = $this->tableSchema->filterLinks;
            if (!empty($filterLinks)) {
                $activeIdx = $this->state->getActiveFilterLink();
                if (isset($filterLinks[$activeIdx])) {
                    $where[] = $filterLinks[$activeIdx]->condition;
                }
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
    protected function formatRow(array $row): array
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
            "post" => "/admin/{$this->moduleLink}/modal/filter",
            "showClear" => $this->state->hasFilters(),
            "dropdowns" => [
                "show" => !empty($this->tableSchema->filters),
                "filters" => $dropdownFilters,
            ],
            "dateFilter" => [
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
    // Form rendering (schema-driven)
    // =========================================================================

    protected function renderForm(?int $id, string $type): string
    {
        if (empty($this->formSchema->fields) || !$this->tableName) {
            return '';
        }

        $this->currentFormId = $id;
        $data = $this->runFormQuery($id);
        $readonly = false;

        if ($type === "edit") {
            $data = $data->fetch();
            $data = $this->addPivotFieldPlaceholders($data);
            $data = $this->formOverride($id, $data);
            $title = "Edit $id";
            $submit = "Save Changes";
        } elseif ($type === "show") {
            $submit = false;
            $data = $data->fetch();
            $data = $this->addPivotFieldPlaceholders($data);
            $title = "View $id";
            $readonly = true;
        } elseif ($type === "create") {
            $title = "Create";
            $submit = "Create";
        }

        $this->registerFunctions($readonly, $type);

        return $this->render("admin/form-modal.html.twig", [
            ...$this->getCommonData(),
            "type" => $type,
            "id" => $id,
            "title" => $title,
            "post" => $id
                ? "/admin/{$this->moduleLink}/$id/update"
                : "/admin/{$this->moduleLink}",
            "submit" => $submit,
            "readonly" => $readonly,
            "labels" => $this->formSchema->getLabels(),
            "data" => $data,
            "modalSize" => $this->formSchema->modalSize->value,
        ]);
    }

    private function runFormQuery(?int $id): array|bool|PDOStatement
    {
        if (is_null($id)) {
            return $this->formSchema->getDefaults();
        }
        $pk = $this->tableSchema->primaryKey;
        return qb()->select($this->formSchema->getSelectExpressions())
            ->from($this->tableName)
            ->where(["$pk = ?"], $id)
            ->execute();
    }

    protected function formOverride(?int $id, array $form): array
    {
        return $form;
    }

    /**
     * Add placeholder entries for pivot fields so form template renders them.
     * The actual values are fetched by getPivotValues() in the control renderer.
     */
    private function addPivotFieldPlaceholders(array $data): array
    {
        $result = [];
        foreach ($this->formSchema->fields as $field) {
            if ($field->hasPivot()) {
                $result[$field->name] = null;
            } elseif (array_key_exists($field->name, $data)) {
                $result[$field->name] = $data[$field->name];
            } else {
                $result[$field->name] = null;
            }
        }
        return $result;
    }

    // =========================================================================
    // Twig function registration
    // =========================================================================

    private function registerFunctions(bool $forceReadonly = false, string $formType = 'create'): void
    {
        $twig = twig();
        $twig->addFunction(new TwigFunction("hasShow", fn(int $id) => $this->hasShow($id)));
        $twig->addFunction(new TwigFunction("hasEdit", fn(int $id) => $this->hasEdit($id)));
        $twig->addFunction(new TwigFunction("hasDelete", fn(int $id) => $this->hasDelete($id)));
        $twig->addFunction(new TwigFunction("isToolbarActionAllowed", fn(string $name) => $this->isToolbarActionAllowed($name)));
        $formRenderer = $this->makeFormRenderer();
        $twig->addFunction(new TwigFunction("control",
            fn(string $col, ?string $val) => $formRenderer->render($col, $val, $forceReadonly, $formType)
        ));
        $twig->addFunction(new TwigFunction("format", fn(string $column, ?string $value) => $this->formatValue($column, $value)));
    }

    private function makeFormRenderer(): FormControlRenderer
    {
        return new FormControlRenderer(
            formSchema: $this->formSchema,
            moduleLink: $this->moduleLink,
            renderer: fn(string $t, array $d) => $this->render($t, $d),
            validationErrors: $this->getValidationErrors(),
            request: $this->request,
            pivotSyncer: new PivotSyncer(),
            currentFormId: $this->currentFormId,
        );
    }

    // =========================================================================
    // Request handling
    // =========================================================================

    private function massageRequest(?int $id, array $request): array
    {
        $this->pendingPivotData = [];

        foreach ($this->formSchema->fields as $field) {
            $column = $field->name;
            $control = $field->control;
            $value = $request[$column] ?? null;
            if ($value === "NULL") {
                $request[$column] = null;
            }
            if ($control == "checkbox") {
                $request[$column] = $value ? 1 : 0;
            }
            if ($control == "multiselect" && $field->hasPivot()) {
                // Store pivot data for later sync, remove from main request
                $this->pendingPivotData[] = [
                    'field' => $field,
                    'values' => is_array($value) ? $value : [],
                ];
                unset($request[$column]);
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
                    $upload_result = (new FileUploadHandler())->handle($is_upload);
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
        $result = qb()->insert($request)
            ->into($this->tableName)
            ->params(array_values($request))
            ->execute();
        if ($result) {
            return db()->lastInsertId();
        }
        return false;
    }

    protected function handleUpdate(int $id, array $request): bool
    {
        $pk = $this->tableSchema->primaryKey;
        $result = qb()->update($request)
            ->params(array_values($request))
            ->table($this->tableName)
            ->where(["$pk = ?"], $id)
            ->execute();
        return (bool) $result;
    }

    protected function handleDestroy(int $id): bool
    {
        $pk = $this->tableSchema->primaryKey;
        $result = qb()->delete()
            ->from($this->tableName)
            ->where(["$pk = ?"], $id)
            ->execute();
        return (bool) $result;
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

    /**
     * Check if a row action is allowed for a given record.
     */
    protected function isActionAllowed(string $name, ?int $id = null): bool
    {
        $action = $this->tableSchema->getRowAction($name);
        if (!$action) {
            return false;
        }
        if ($action->requiresForm && empty($this->formSchema->fields)) {
            return false;
        }
        if ($action->permission && !$this->checkPermission($action->permission)) {
            return false;
        }
        return true;
    }

    /**
     * Check if a toolbar action is allowed.
     */
    protected function isToolbarActionAllowed(string $name): bool
    {
        $action = $this->tableSchema->getToolbarAction($name);
        if (!$action) {
            return false;
        }
        if ($action->requiresForm && empty($this->formSchema->fields)) {
            return false;
        }
        if ($action->permission && !$this->checkPermission($action->permission)) {
            return false;
        }
        return true;
    }

    protected function hasExport(): bool
    {
        return $this->isToolbarActionAllowed('export');
    }

    protected function hasCreate(): bool
    {
        return $this->isToolbarActionAllowed('create');
    }

    protected function hasShow(int $id): bool
    {
        return $this->isActionAllowed('show', $id);
    }

    protected function hasEdit(int $id): bool
    {
        return $this->isActionAllowed('edit', $id);
    }

    protected function hasDelete(int $id): bool
    {
        return $this->isActionAllowed('delete', $id);
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
        if ($this->cachedModule !== null) {
            return $this->cachedModule;
        }
        $link = $this->getModuleLink();
        $this->cachedModule = db()->fetch("SELECT *
            FROM modules
            WHERE enabled = 1 AND link = ?", [$link]);
        return $this->cachedModule;
    }

    /**
     * Derive the module link from the child controller's #[Group] pathPrefix.
     */
    private function getModuleLink(): string
    {
        $reflection = new \ReflectionClass(static::class);
        $attrs = $reflection->getAttributes(Group::class);
        if (!empty($attrs)) {
            return trim($attrs[0]->newInstance()->pathPrefix, '/');
        }
        return '';
    }

    private function init(): void
    {
        $this->hasPermission();
        $module = $this->getModule();

        if ($module) {
            $this->moduleTitle = $module['title'];
            $this->moduleLink = $module['link'];
            $this->moduleIcon = $module['icon'];
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
                "links" => $sidebar_provider->getLinks(null, user()),
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

}
