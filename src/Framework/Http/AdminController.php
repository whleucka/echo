<?php

namespace Echo\Framework\Http;

use App\Models\FileInfo;
use App\Services\Admin\SidebarService;
use Echo\Framework\Audit\AuditLogger;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Session\Flash;
use PDOStatement;
use RuntimeException;
use Throwable;
use Twig\TwigFunction;

#[Group(path_prefix: "/admin", middleware: ["auth"])]
abstract class AdminController extends Controller
{
    protected array $actions = [];
    protected string $module_icon = "";
    protected string $module_link = "";
    protected string $module_title = "";

    protected string $table_pk = "id";
    protected array $table_columns = [];
    protected array $table_actions = [];
    protected array $table_format = [];
    protected array $table_joins = [];

    protected int $per_page = 10;
    protected int $page = 1;
    protected int $total_pages = 1;
    protected int $total_results = 0;
    protected int $pagination_links = 2;

    protected array $query_where = [];
    protected array $query_params = [];
    protected string $query_order_by = "id";
    protected string $query_sort = "DESC";

    protected array $form_columns = [];
    protected array $form_controls = [];
    protected array $form_dropdowns = [];
    protected array $form_datalist = [];
    protected array $form_readonly = [];
    protected array $form_disabled = [];
    protected array $form_defaults = [];

    protected array $file_accept = [];

    protected string $filter_date_column = "created_at";
    protected string $filter_date_start = "";
    protected string $filter_date_end = "";
    protected array $filter_dropdowns = [];
    protected array $filter_links = [];
    protected array $search_columns = [];
    protected string $search_term = "";
    protected int $active_filter_link = 0;

    protected bool $has_edit = true;
    protected bool $has_create = true;
    protected bool $has_delete = true;
    protected bool $has_export = true;
    protected bool $has_show = true;

    protected array $validation_rules = [];

    public function __construct(private ?string $table_name = null)
    {
        $this->init();
    }

    #[Get("/", "admin.index")]
    public function index(): string
    {
        return $this->renderModule($this->getModuleData());
    }

    #[Get("/page/{page}", "admin.page")]
    public function page(int $page): string
    {
        $this->setSession("page", $page);
        return $this->index();
    }

    #[Get("/sort/{idx}", "admin.sort")]
    public function sort(int $idx): string
    {
        $columns = array_values($this->table_columns);
        $column = $this->getAlias($columns[$idx]);
        $order_by = $this->getSession("order_by") ?? $this->query_order_by;
        $sort = $this->getSession("sort") ?? $this->query_sort;
        if ($order_by == $column) {
            // Switch direction
            $direction = $sort === "ASC" ? "DESC" : "ASC";
            $this->setSession("sort", $direction);
        } else {
            $this->setSession("order_by", $column);
            $this->setSession("sort", "DESC");
        }
        return $this->index();
    }

    #[Get("/export-csv", "admin.export-csv")]
    public function export_csv(): mixed
    {
        if (!$this->hasExport()) {
            return $this->permissionDenied();
        }
        $this->props();

        $rows = $this->runTableQuery(false)->fetchAll();
        if ($rows) $this->streamCSV($rows, $this->table_columns, $this->module_link . '_export.csv');
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
        $this->setSession("filter_link", $index);
        $this->setSession("page", 1);
        return $this->index();
    }

    #[Get("/filter/count/{index}", "admin.filter-count")]
    public function filter_count(int $index)
    {
        $filters = array_values($this->filter_links);
        $filter_where = $filters[$index];
        $this->query_where[] = $filter_where;
        $this->props(false);
        return $this->runTableQuery(false)->rowCount();
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
                $this->clearFilters();
            } else {
                $this->handleRequest($valid);
            }
            header("HX-Retarget: #module");
            header("HX-Reselect: #module");
            header("HX-Reswap: outerHTML");
            return $this->index();
        }
        // Request is invalid
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
            $request = $this->massageRequest(null, (array)$valid);
            $id = $this->handleStore($request);
            if ($id) {
                Flash::add("success", "Successfully created record");
                header("HX-Retarget: #module");
                header("HX-Reselect: #module");
                header("HX-Reswap: outerHTML");
                return $this->index();
            }
        }
        // Request is invalid
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
            $request = $this->massageRequest($id, (array)$valid);
            $result = $this->handleUpdate($id, $request);
            if ($result) {
                Flash::add("success", "Successfully updated record");
                header("HX-Retarget: #module");
                header("HX-Reselect: #module");
                header("HX-Reswap: outerHTML");
                return $this->index();
            }
        }
        // Request is invalid
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

    private function massageRequest(?int $id, array $request): array
    {
        foreach ($this->form_controls as $column => $control) {
            $value = $request[$column] ?? null;
            // Handle null
            if ($value === "NULL") $request[$column] = null;
            // Handle checkboxes
            if ($control == "checkbox") {
                $request[$column] = $value ? 1 : 0;
            }
            if (in_array($control, ["file", "image"])) {
                $delete_file = $this->request->request->delete_file;
                $is_upload = $this->request->files->$column ?? false;
                if (isset($delete_file[$column])) {
                    // Delete the file
                    $fi = new FileInfo($delete_file[$column]);
                    if ($fi) {
                        $fi->delete();
                        $request[$column] = null;
                    }
                } else if ($is_upload) {
                    $upload_result = $this->handleFileUpload($is_upload);
                    if ($upload_result) {
                        $request[$column] = $upload_result;
                    }
                } else {
                    // The file is not being modified
                    unset($request[$column]);
                }
            }
        }
        return $request;
    }

    private function setSession(string $key, mixed $value): void
    {
        $data = session()->get($this->module_link);
        $data[$key] = $value;
        session()->set($this->module_link, $data);
    }

    private function getSession(string $key): mixed
    {
        $data = session()->get($this->module_link);
        return $data[$key] ?? null;
    }

    private function setFilter(string $filter, mixed $value): void
    {
        $filters = $this->getSession("filters");
        $filters[$filter] = $value;
        $this->setSession("filters", $filters);
    }

    private function removeFilter(string $filter, mixed $value): void
    {
        $filters = $this->getSession("filters");
        unset($filters[$filter]);
        $this->setSession("filters", $filters);
    }

    private function getFilter(string $filter): mixed
    {
        $filters = $this->getSession("filters");
        return $filters[$filter] ?? null;
    }

    private function removeSession(string $key): void
    {
        $data = session()->get($this->module_link);
        unset($data[$key]);
        session()->set($this->module_link, $data);
    }

    private function registerFunctions()
    {
        $has_export = new TwigFunction("has_export", fn() => $this->hasExport());
        $has_create = new TwigFunction("has_create", fn() => $this->hasCreate());
        $has_edit = new TwigFunction("has_edit", fn(int $id) => $this->hasEdit($id));
        $has_show = new TwigFunction("has_show", fn(int $id) => $this->hasShow($id));
        $has_delete = new TwigFunction("has_delete", fn(int $id) => $this->hasDelete($id));
        $has_row_actions = new TwigFunction("has_row_actions", fn() => $this->has_show || $this->has_edit || $this->has_delete);
        $control = new TwigFunction("control", fn(string $column, ?string $value) => $this->control($column, $value));
        $format = new TwigFunction("format", fn(string $column, ?string $value) => $this->format($column, $value));
        twig()->addFunction($has_export);
        twig()->addFunction($has_create);
        twig()->addFunction($has_edit);
        twig()->addFunction($has_show);
        twig()->addFunction($has_delete);
        twig()->addFunction($has_row_actions);
        twig()->addFunction($control);
        twig()->addFunction($format);
    }

    private function runTableQuery(bool $limit = true): bool|PDOStatement
    {
        $tables = $this->table_name . ' ' . implode(" ", $this->table_joins);
        $q = qb()->select(array_values($this->table_columns))
            ->from($tables)
            ->params($this->query_params)
            ->where($this->query_where)
            ->orderBy(["{$this->query_order_by} {$this->query_sort}"]);

        if ($limit) {
            $limit = $this->per_page;
            $offset = $this->per_page * ($this->page - 1);
            $q->limit($limit)->offset($offset);
        }

        return $q->execute();
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
        return qb()->select(array_values($this->form_columns))
            ->from($this->table_name)
            ->where(["$this->table_pk = ?"], $id)
            ->execute();
    }

    private function getSubquery(string $str): string
    {
        $str = explode(" as ", $str);
        return $str[0];
    }

    private function getAlias(string $str): string
    {
        $str = explode(" as ", $str);
        return end($str);
    }

    private function format(string $column, ?string $value)
    {
        if (isset($this->table_format[$column])) {
            $format = $this->table_format[$column];
            return match ($format) {
                "check" => $this->renderFormat("check", $column, $value),
                default => is_callable($format) ? $format($column, $value) : $value
            };
        }
        return $value;
    }

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
                    "checked" => $value != false
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
                default => is_callable($control) ? $control($column, $value) : $value
            };
        }
        // No control output
        return $value;
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

    private function renderFormat(string $type, string $column, ?string $value, array $data = [])
    {
        $default = [
            "class" => $this->getValidationClass($column, 'table-format'),
            "id" => $column,
            "title" => array_search($column, $this->table_columns),
            "value" => $value,
        ];
        $template_data = array_merge($default, $data);
        return $this->render("admin/format/$type.html.twig", $template_data);
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
            $this->setFilter("date_start", $request->filter_date_start);
            $this->setFilter("date_end", $request->filter_date_end);
            $this->setSession("page", 1);
        }
        if (isset($request->filter_search)) {
            $this->setFilter("search", $request->filter_search);
            $this->setSession("page", 1);
        }
        if (isset($request->filter_dropdowns)) {
            foreach ($request->filter_dropdowns as $i => $value) {
                if ($value !== 'NULL') {
                    $this->setFilter("dropdowns_" . $i, $value);
                } else {
                    $this->removeFilter("dropdowns_" . $i, $value);
                }
            }
        }
    }

    private function clearFilters(): void
    {
        $this->setSession("page", 1);
        $this->removeSession("filters");
    }

    private function streamCSV(iterable $rows, array $columns = [], string $filename = 'export.csv')
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

        if (!empty($columns)) {
            fputcsv($output_handle, array_keys($columns));
        }

        foreach ($rows as $row) {
            $row = $this->exportOverride($row);
            if (!empty($columns) && array_is_list($row) === false) {
                $ordered_row = [];
                foreach ($columns as $title => $subquery) {
                    $key = $this->getAlias($subquery);
                    $ordered_row[] = $row[$key] ?? '';
                }
                fputcsv($output_handle, $ordered_row);
            } else {
                fputcsv($output_handle, $row);
            }

            flush(); // Free memory buffer
        }

        fclose($output_handle);
        exit;
    }

    private function getModule()
    {
        $link = explode('.', request()->getAttribute("route")["name"])[0];
        return db()->fetch("SELECT * 
            FROM modules 
            WHERE enabled = 1 AND link = ?", [$link]);
    }

    private function init()
    {
        $this->hasPermission();
        $module = $this->getModule();

        // Check if module exists
        if ($module) {
            $this->module_title = $module['title'];
            $this->module_link = $module['link'];
            $this->module_icon = $module['icon'];
        } else {
            $this->pageNotFound();
        }

        // Table columns must always contain table_pk
        if (!in_array($this->table_pk, $this->table_columns)) {
            $columns[strtoupper($this->table_pk)] = $this->table_pk;
            $this->table_columns = [
                ...$columns,
                ...$this->table_columns,
            ];
        }

    }

    private function columnExists(string $needle, array $haystack): bool
    {
        foreach ($haystack as $one) {
            $column = $this->getAlias($one);
            if ($needle == $column) return true;
        }
        return false;
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

    private function hasPermission()
    {
        $module = $this->getModule();
        // Check module permission
        if (user()->role !== 'admin') {
            // Maybe permission is granted to them
            $permission = user()->hasPermission($module["id"]);
            if (!$permission) {
                $this->permissionDenied();
            }
        }
        return true;
    }

    private function checkPermission(string $mode)
    {
        $module = $this->getModule();
        // Check module (create,edit,delete) permission
        if (user()->role !== 'admin') {
            // Maybe permission is granted to them
            $permission = user()->hasModePermission($module["id"], $mode);
            return $permission;
        }
        return true;
    }

    protected function handleTableAction(int $id, string $action)
    {
        $exec = match ($action) {
            "delete" => function($id) {
                if ($this->hasDelete($id)) {
                    $result = $this->handleDestroy($id);
                    if ($result) {
                        Flash::add("success", "Successfully deleted record");
                    }
                } else {
                    Flash::add("warning", "Cannot delete record $id");
                }
            },
            default => fn() => Flash::add("warning", "Unknown action")
        };
        if (is_callable($exec)) {
            // Execute the action
            $exec($id);
        }
    }

    protected function processSession()
    {
        // Assign module properties
        if ($this->table_name && !empty($this->table_columns)) {
            $this->active_filter_link = $this->getSession("filter_link") ?? $this->active_filter_link;
            $this->page = $this->getSession("page") ?? $this->page;
            $this->query_order_by = $this->getSession("order_by") ?? $this->query_order_by;
            $this->query_sort = $this->getSession("sort") ?? $this->query_sort;
            $this->search_term = $this->getFilter("search") ?? $this->search_term;
            $this->filter_date_start = $this->getFilter("date_start") ?? $this->filter_date_start;
            $this->filter_date_end = $this->getFilter("date_end") ?? $this->filter_date_end;
        }
    }

    protected function props(bool $filter_links = true)
    {
        $this->processSession();

        // Filter links
        if ($filter_links && !empty($this->filter_links)) {
            $filters = array_values($this->filter_links);
            $filter_where = $filters[$this->active_filter_link];
            $this->query_where[] = $filter_where;
        }

        // Search filter
        if ($this->search_term) {
            $where = [];
            foreach ($this->search_columns as $title) {
                $query = $this->table_columns[$title];
                $column = $this->getSubquery($query);
                if ($column) {
                    $where[] = "($column LIKE ?)";
                    $this->query_params[] = "%{$this->search_term}%";
                }
            }
            $this->query_where[] = '(' . implode(" OR ", $where) . ')';
        }

        // Datetime filter
        if ($this->filter_date_column && $this->filter_date_start && $this->filter_date_end) {
            $this->query_where[] = sprintf("%s BETWEEN ? AND ?", $this->filter_date_column);
            $this->query_params[] = $this->filter_date_start;
            $this->query_params[] = $this->filter_date_end;
        } else if ($this->filter_date_column && $this->filter_date_start && !$this->filter_date_end) {
            $this->query_where[] = sprintf("%s >= ?", $this->filter_date_column);
            $this->query_params[] = $this->filter_date_start;
        } else if ($this->filter_date_column && !$this->filter_date_start && $this->filter_date_end) {
            $this->query_where[] = sprintf("%s <= ?", $this->filter_date_column);
            $this->query_params[] = $this->filter_date_end;
        }

        // Dropdown filters
        $i = 0;
        foreach ($this->filter_dropdowns as $column => $query) {
            $selected = $this->getFilter("dropdowns_" . $i++);
            if ($selected) {
                $this->query_where[] = "$column = ?";
                $this->query_params[] = $selected;
            }
        }
    }

    protected function exportOverride(array $row): array
    {
        return $row;
    }

    protected function tableOverride(array $row): array
    {
        return $row;
    }

    protected function formOverride(?int $id, array $form): array
    {
        return $form;
    }

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

    protected function renderModule(array $data): string
    {
        return $this->render("admin/module.html.twig", $data);
    }

    protected function renderFilter(): string
    {
        if (empty($this->table_columns) || !$this->table_name) return '';

        $this->props();

        return $this->render("admin/filter.html.twig", [
            "post" => "/admin/{$this->module_link}/modal/filter",
            "show_clear" => !empty($this->getSession("filters")),
            "dropdowns" => [
                "show" => !empty($this->filter_dropdowns),
                "filters" => $this->dropdownFilters(),
            ],
            "date_filter" => [
                "show" => $this->filter_date_column && $this->columnExists($this->filter_date_column, $this->table_columns),
                "start" => $this->filter_date_start,
                "end" => $this->filter_date_end,
            ],
            "search" => [
                "show" => !empty($this->search_columns),
                "term" => $this->search_term,
            ],
        ]);
    }

    private function dropdownFilters(): array
    {
        $filters = [];
        $i = 0;
        foreach ($this->filter_dropdowns as $column => $query) {
            $selected = $this->getFilter("dropdowns_" . $i++);
            $sql = $this->filter_dropdowns[$column];
            $filters[] = [
                "label" => $this->getTableTitle($column),
                "selected" => $selected,
                "options" => key_exists($column, $this->filter_dropdowns) && is_string($this->filter_dropdowns[$column])
                    ? db()->fetchAll($this->filter_dropdowns[$column])
                    : $this->filter_dropdowns[$column] ?? [],
            ];
        }
        return $filters;
    }

    private function getTableTitle(string $column)
    {
        foreach ($this->table_columns as $title => $query) {
            $alias = $this->getAlias($query);
            if ($alias === $column) return $title;
        }
        return null;
    }

    protected function renderTable(): string
    {
        if (empty($this->table_columns) || !$this->table_name) return '';

        $this->props();

        // Total results
        $this->total_results = $this->runTableQuery(false)->rowCount();
        $this->total_pages = ceil($this->total_results / $this->per_page);
        $data = $this->runTableQuery()->fetchAll();

        foreach ($data as $i => $row) {
            $data[$i] = $this->tableOverride($row);
        }

        // Table caption
        $start = 1 + ($this->page * $this->per_page) - $this->per_page;
        $end = min($this->page * $this->per_page, $this->total_results);

        // Setup functions
        $this->registerFunctions();

        // Setup headers
        $headers = [];
        foreach ($this->table_columns as $title => $query) {
            $headers[$this->getAlias($query)] = $title;
        }

        // Setup table actions
        if ($this->has_delete) {
            $this->table_actions[] = [
                "value" => "delete",
                "label" => "Delete",
            ];
        }

        return $this->render("admin/table.html.twig", [
            ...$this->getCommonData(),
            "headers" => $headers,
            "has_delete" => $this->has_delete,
            "has_edit" => $this->has_edit,
            "has_create" => $this->has_create,
            "table_actions" =>$this->table_actions,
            "order_by" => $this->query_order_by,
            "filters" => [
                "show" => !empty($this->search_columns) || $this->filter_date_column != '' || !empty($this->filter_dropdowns),
                "show_clear" => !empty($this->getSession("filters")),
                "filter_links" => [
                    "show" => !empty($this->filter_links),
                    "active" => $this->active_filter_link,
                    "links" => array_keys($this->filter_links),
                ],
                "order_by" => $this->query_order_by,
                "sort" => $this->query_sort,
            ],
            "caption" => $this->total_pages > 1
                ? "Showing {$start}–{$end} of {$this->total_results} results"
                : "",
            "data" => [
                "rows" => $data,
            ],
            "pagination" => [
                "page" => $this->page,
                "total_pages" => $this->total_pages,
                "total_results" => $this->total_results,
                "links" => $this->pagination_links,
            ]
        ]);
    }

    protected function renderForm(?int $id, string $type): string
    {
        if (empty($this->form_columns) || !$this->table_name) return '';

        $data = $this->runFormQuery($id);

        if ($type === "edit") {
            $data = $data->fetch();
            $data = $this->formOverride($id, $data);
            $title = "Edit $id";
            $submit = "Save Changes";
        } else if ($type === "show") {
            $submit = false;
            $data = $data->fetch();
            $title = "View $id";
            foreach ($data as $column => $value) {
                // All columns are readonly
                if (!in_array($column, $this->form_readonly)) {
                    $this->form_readonly[] = $column;
                }
            }
        } else if ($type === "create") {
            $title = "Create";
            $submit = "Create";
        }

        // Setup functions
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

    protected function getCommonData(): array
    {
        // Refresh module in case there are changes
        $module = $this->getModule();
        $sidebar_provider = new SidebarService;
        return [
            "sidebar" => [
                "hide" => $sidebar_provider->getState(),
                "links" => $sidebar_provider->getLinks([], [], user())
            ],
            "user" => [
                "name" => $this->user->first_name . " " . $this->user->surname,
                "email" => $this->user->email,
                "avatar" => $this->user->avatar
                    ? $this->user->avatar()
                    : $this->user->gravatar(38)
            ],
            "module" => [
                "link" => $module['link'],
                "title" => $module['title'],
                "icon" => $module['icon'],
            ]
        ];
    }

    protected function handleFileUpload(array $file): int|false
    {
        $upload_dir = config("paths.uploads");
        if (!is_dir($upload_dir)) {
            $result = mkdir($upload_dir, 0775, true);
            if (!$result) {
                throw new \RuntimeException("Cannot create uploads directory" . $file['error']);
            }
        }

        // Check for upload errors
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new \RuntimeException("File upload error: " . $file['error']);
        }

        // Sanitize and generate unique filename
        $og_name = basename($file['name']);
        $extension    = pathinfo($og_name, PATHINFO_EXTENSION);
        $unique_name   = uniqid('file_', true) . ($extension ? ".$extension" : "");
        $target_path   = sprintf("%s/%s", $upload_dir, $unique_name);

        // Move the uploaded file
        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            throw new \RuntimeException("Failed to move uploaded file.");
        }

        // Gather file info
        $mime_type = mime_content_type($target_path);
        $file_size = filesize($target_path);
        $relative_path = sprintf("/uploads/%s", $unique_name);

        // Insert file information
        $result = FileInfo::create([
            "original_name" => $og_name,
            "stored_name" => $unique_name,
            "path" => $relative_path,
            "mime_type" => $mime_type,
            "size" => $file_size,
        ]);

        return $result->id ?? false;
    }

    protected function handleDestroy(int $id): bool
    {
        try {
            // Capture old values before delete for audit
            $oldValues = db()->fetch(
                "SELECT * FROM {$this->table_name} WHERE {$this->table_pk} = ?",
                [$id]
            );

            $result = qb()->delete()
                ->from($this->table_name)
                ->where(["{$this->table_pk} = ?"], $id)
                ->execute();
            if ($result) {
                // Log the deletion
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

    protected function handleUpdate(int $id, array $request): bool
    {
        try {
            // Capture old values before update for audit
            $oldValues = db()->fetch(
                "SELECT * FROM {$this->table_name} WHERE {$this->table_pk} = ?",
                [$id]
            );

            // Set the params before the where clause
            // so that we get the correct query param count
            $result = qb()->update($request)
                ->params(array_values($request))
                ->table($this->table_name)
                ->where(["{$this->table_pk} = ?"], $id)
                ->execute();
            if ($result) {
                // Capture new values after update for audit
                $newValues = db()->fetch(
                    "SELECT * FROM {$this->table_name} WHERE {$this->table_pk} = ?",
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

    protected function handleStore(array $request): mixed
    {
        try {
            $result = qb()->insert($request)
                ->into($this->table_name)
                ->params(array_values($request))
                ->execute();
            if ($result) {
                // Returns the inserted ID
                $id = db()->lastInsertId();

                // Capture new record for audit
                $newValues = db()->fetch(
                    "SELECT * FROM {$this->table_name} WHERE {$this->table_pk} = ?",
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
}
