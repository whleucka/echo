<?php

namespace Echo\Framework\Admin\Schema;

final class TableSchema
{
    /**
     * @param string                      $table
     * @param string                      $primaryKey
     * @param ColumnDefinition[]          $columns
     * @param FilterDefinition[]          $filters
     * @param FilterLinkDefinition[]      $filterLinks
     * @param ActionDefinition[]          $actions         Bulk actions
     * @param RowActionDefinition[]       $rowActions      Per-row actions (show, edit, delete)
     * @param ToolbarActionDefinition[]   $toolbarActions  Toolbar actions (create, export)
     * @param string[]                    $joins           Raw SQL JOIN clauses
     * @param string                      $defaultOrderBy
     * @param string                      $defaultSort
     * @param string                      $dateColumn
     * @param PaginationConfig            $pagination
     */
    public function __construct(
        public readonly ?string $table,
        public readonly string $primaryKey,
        public readonly array $columns,
        public readonly array $filters,
        public readonly array $filterLinks,
        public readonly array $actions,
        public readonly array $rowActions,
        public readonly array $toolbarActions,
        public readonly array $joins,
        public readonly string $defaultOrderBy,
        public readonly string $defaultSort,
        public readonly string $dateColumn,
        public readonly PaginationConfig $pagination,
    ) {}

    /**
     * Get SELECT expressions for all columns.
     *
     * @return string[]
     */
    public function getSelectExpressions(): array
    {
        return array_map(
            fn(ColumnDefinition $col) => $col->getSelectExpression(),
            $this->columns
        );
    }

    /**
     * Get columns marked as searchable.
     *
     * @return ColumnDefinition[]
     */
    public function getSearchableColumns(): array
    {
        return array_values(
            array_filter($this->columns, fn(ColumnDefinition $col) => $col->searchable)
        );
    }

    /**
     * Find a column definition by name.
     */
    public function getColumn(string $name): ?ColumnDefinition
    {
        foreach ($this->columns as $col) {
            if ($col->name === $name) {
                return $col;
            }
        }
        return null;
    }

    /**
     * Check if a specific row action is defined.
     */
    public function hasRowAction(string $name): bool
    {
        foreach ($this->rowActions as $action) {
            if ($action->name === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if a specific toolbar action is defined.
     */
    public function hasToolbarAction(string $name): bool
    {
        foreach ($this->toolbarActions as $action) {
            if ($action->name === $name) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get a row action definition by name.
     */
    public function getRowAction(string $name): ?RowActionDefinition
    {
        foreach ($this->rowActions as $action) {
            if ($action->name === $name) {
                return $action;
            }
        }
        return null;
    }

    /**
     * Get a toolbar action definition by name.
     */
    public function getToolbarAction(string $name): ?ToolbarActionDefinition
    {
        foreach ($this->toolbarActions as $action) {
            if ($action->name === $name) {
                return $action;
            }
        }
        return null;
    }

    /**
     * Check if the primary key is already included in columns.
     */
    public function hasPrimaryKeyColumn(): bool
    {
        foreach ($this->columns as $col) {
            if ($col->name === $this->primaryKey) {
                return true;
            }
        }
        return false;
    }
}
