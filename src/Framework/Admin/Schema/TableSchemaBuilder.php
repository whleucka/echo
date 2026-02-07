<?php

namespace Echo\Framework\Admin\Schema;

class TableSchemaBuilder
{
    private string $primaryKey = 'id';
    private array $columns = [];
    private array $filters = [];
    private array $filterLinks = [];
    private array $actions = [];
    private array $joins = [];
    private string $defaultOrderBy = 'id';
    private string $defaultSort = 'DESC';
    private string $dateColumn = 'created_at';
    private int $perPage = 10;

    public function __construct(private ?string $table = null) {}

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

    /**
     * Add a raw SQL JOIN clause.
     *
     * Usage:
     *   $builder->join('LEFT JOIN users ON users.id = audits.user_id')
     */
    public function join(string $sql): self
    {
        $this->joins[] = $sql;
        return $this;
    }

    /**
     * Define a dropdown filter.
     *
     * Usage:
     *   $builder->filter('role', 'role')->options([...])
     *   $builder->filter('user', 'audits.user_id')->optionsFrom('SELECT ...')
     */
    public function filter(string $name, string $column): TableFilterBuilder
    {
        $filterBuilder = new TableFilterBuilder($name, $column);
        $this->filters[] = $filterBuilder;
        return $filterBuilder;
    }

    /**
     * Define a quick-filter link button.
     *
     * Usage:
     *   $builder->filterLink('Created', "audits.event = 'created'")
     */
    public function filterLink(string $label, string $condition): self
    {
        $this->filterLinks[] = ['label' => $label, 'condition' => $condition];
        return $this;
    }

    /**
     * Define a bulk table action.
     *
     * Usage:
     *   $builder->bulkAction('delete', 'Delete')
     */
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
