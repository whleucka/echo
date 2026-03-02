<?php

namespace Echo\Framework\Admin;

use Echo\Framework\Admin\Schema\TableSchema;

class ModuleState
{
    public function __construct(private string $moduleKey) {}

    public function getPage(): int
    {
        return $this->get('page', 1);
    }

    public function setPage(int $page): void
    {
        $this->set('page', $page);
    }

    public function getPerPage(int $default): int
    {
        return $this->get('per_page', $default);
    }

    public function setPerPage(int $count): void
    {
        $this->set('per_page', $count);
    }

    public function getOrderBy(string $default): string
    {
        return $this->get('order_by', $default);
    }

    public function setOrderBy(string $col): void
    {
        $this->set('order_by', $col);
    }

    public function getSort(string $default): string
    {
        return $this->get('sort', $default);
    }

    public function setSort(string $dir): void
    {
        $this->set('sort', $dir);
    }

    public function getActiveFilterLink(): int
    {
        return $this->get('filter_link', 0);
    }

    public function setActiveFilterLink(int $idx): void
    {
        $this->set('filter_link', $idx);
    }

    public function getFilter(string $key): mixed
    {
        $filters = $this->getFilters();
        return $filters[$key] ?? null;
    }

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

    public function getFilters(): array
    {
        return $this->get('filters', []);
    }

    public function clearFilters(): void
    {
        $this->set('filters', []);
        $this->setPage(1);
    }

    public function hasFilters(): bool
    {
        return !empty($this->getFilters());
    }

    // =========================================================================
    // URL State — encode/decode table state as query parameters
    // =========================================================================

    /**
     * Query parameter mapping:
     *   page   -> page number
     *   pp     -> per-page count
     *   sort   -> column name to order by
     *   dir    -> asc or desc
     *   fl     -> filter link index
     *   search -> search term
     *   ds     -> date start
     *   de     -> date end
     *   f[0]   -> dropdown filter value (by index)
     *   edit   -> record ID to open in edit modal
     *   show   -> record ID to open in show modal
     */

    /**
     * Hydrate session state from URL query parameters.
     *
     * Only recognized params are applied. Unknown params are ignored.
     * When query params are present, they take precedence over session state.
     *
     * @param array       $query  The $_GET array
     * @param TableSchema $schema Used to validate column names and per-page options
     * @return array{edit: ?int, show: ?int}  Any deep-link actions to perform
     */
    public function hydrateFromQuery(array $query, TableSchema $schema): array
    {
        $actions = ['edit' => null, 'show' => null];

        if (empty($query)) {
            return $actions;
        }

        // Page
        if (isset($query['page'])) {
            $page = filter_var($query['page'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($page !== false) {
                $this->setPage($page);
            }
        }

        // Per-page
        if (isset($query['pp'])) {
            $pp = filter_var($query['pp'], FILTER_VALIDATE_INT);
            if ($pp !== false && in_array($pp, $schema->pagination->perPageOptions)) {
                $this->setPerPage($pp);
            }
        }

        // Sort column — validate against known column names
        if (isset($query['sort'])) {
            $sortCol = $query['sort'];
            $validColumn = false;
            foreach ($schema->columns as $col) {
                if ($col->name === $sortCol || $col->expression === $sortCol) {
                    $validColumn = true;
                    break;
                }
            }
            if ($validColumn) {
                $this->setOrderBy($sortCol);
            }
        }

        // Sort direction
        if (isset($query['dir'])) {
            $dir = strtoupper($query['dir']);
            if (in_array($dir, ['ASC', 'DESC'])) {
                $this->setSort($dir);
            }
        }

        // Filter link index
        if (isset($query['fl'])) {
            $fl = filter_var($query['fl'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
            if ($fl !== false && isset($schema->filterLinks[$fl])) {
                $this->setActiveFilterLink($fl);
            }
        }

        // Search
        if (isset($query['search'])) {
            $search = trim($query['search']);
            if ($search !== '') {
                $this->setFilter('search', $search);
            } else {
                $this->removeFilter('search');
            }
        }

        // Date range
        if (isset($query['ds'])) {
            $ds = trim($query['ds']);
            if ($ds !== '') {
                $this->setFilter('date_start', $ds);
            } else {
                $this->removeFilter('date_start');
            }
        }
        if (isset($query['de'])) {
            $de = trim($query['de']);
            if ($de !== '') {
                $this->setFilter('date_end', $de);
            } else {
                $this->removeFilter('date_end');
            }
        }

        // Dropdown filters
        if (isset($query['f']) && is_array($query['f'])) {
            foreach ($query['f'] as $i => $value) {
                $idx = filter_var($i, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
                if ($idx !== false && isset($schema->filters[$idx])) {
                    if ($value !== '' && $value !== 'NULL') {
                        $this->setFilter("dropdowns_" . $idx, $value);
                    } else {
                        $this->removeFilter("dropdowns_" . $idx);
                    }
                }
            }
        }

        // Deep-link actions (edit / show)
        if (isset($query['edit'])) {
            $editId = filter_var($query['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($editId !== false) {
                $actions['edit'] = $editId;
            }
        }
        if (isset($query['show'])) {
            $showId = filter_var($query['show'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
            if ($showId !== false) {
                $actions['show'] = $showId;
            }
        }

        return $actions;
    }

    /**
     * Build a query string representing the current state.
     *
     * Only includes params that differ from defaults, keeping URLs clean.
     *
     * @param TableSchema $schema   Used to determine defaults
     * @param array       $overrides  Override specific params (e.g., ['page' => 3])
     * @return string  Query string without leading "?"
     */
    public function toQueryString(TableSchema $schema, array $overrides = []): string
    {
        $params = [];

        $page = $overrides['page'] ?? $this->getPage();
        if ($page > 1) {
            $params['page'] = $page;
        }

        $perPage = $overrides['pp'] ?? $this->getPerPage($schema->pagination->perPage);
        if ($perPage !== $schema->pagination->perPage) {
            $params['pp'] = $perPage;
        }

        $orderBy = $overrides['sort'] ?? $this->getOrderBy($schema->defaultOrderBy);
        if ($orderBy !== $schema->defaultOrderBy) {
            $params['sort'] = $orderBy;
        }

        $sort = $overrides['dir'] ?? $this->getSort($schema->defaultSort);
        if ($sort !== $schema->defaultSort) {
            $params['dir'] = strtolower($sort);
        }

        $fl = $overrides['fl'] ?? $this->getActiveFilterLink();
        if ($fl > 0) {
            $params['fl'] = $fl;
        }

        $search = $overrides['search'] ?? $this->getFilter('search');
        if ($search) {
            $params['search'] = $search;
        }

        $ds = $overrides['ds'] ?? $this->getFilter('date_start');
        if ($ds) {
            $params['ds'] = $ds;
        }

        $de = $overrides['de'] ?? $this->getFilter('date_end');
        if ($de) {
            $params['de'] = $de;
        }

        // Dropdown filters
        foreach ($schema->filters as $i => $filter) {
            $key = "dropdowns_" . $i;
            $value = $overrides["f"][$i] ?? $this->getFilter($key);
            if ($value) {
                $params["f[$i]"] = $value;
            }
        }

        return http_build_query($params);
    }

    // =========================================================================
    // Internal helpers
    // =========================================================================

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
