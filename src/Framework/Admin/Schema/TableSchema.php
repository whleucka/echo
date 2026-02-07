<?php

namespace Echo\Framework\Admin\Schema;

final class TableSchema
{
    /**
     * @param string                 $table
     * @param string                 $primaryKey
     * @param ColumnDefinition[]     $columns
     * @param FilterDefinition[]     $filters
     * @param FilterLinkDefinition[] $filterLinks
     * @param ActionDefinition[]     $actions
     * @param string[]               $joins         Raw SQL JOIN clauses
     * @param string                 $defaultOrderBy
     * @param string                 $defaultSort
     * @param string                 $dateColumn
     * @param PaginationConfig       $pagination
     */
    public function __construct(
        public readonly ?string $table,
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
