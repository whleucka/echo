<?php

namespace Echo\Framework\Admin;

use Echo\Framework\Admin\Schema\TableSchema;
use PDO;

class QueryBuilderDataSource implements TableDataSource
{
    public function fetch(
        TableSchema $schema,
        int $page,
        int $perPage,
        array $perPageOptions,
        string $orderBy,
        string $sort,
        array $whereConditions,
        array $whereParams,
    ): TableResult {
        $from = $schema->table . ' ' . implode(' ', $schema->joins);
        $select = $schema->getSelectExpressions();

        // Ensure primary key is in the SELECT
        if (!$schema->hasPrimaryKeyColumn()) {
            array_unshift($select, $schema->primaryKey);
        }

        // Count total matching rows
        $totalRows = $this->countRows($from, $whereConditions, $whereParams);

        // Fetch the page
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
            totalRows: $totalRows,
            page: $page,
            perPage: $perPage,
            perPageOptions: $perPageOptions,
            totalPages: $totalRows > 0 ? (int) ceil($totalRows / $perPage) : 1,
        );
    }

    /**
     * Count total rows matching the WHERE conditions (no limit/offset).
     */
    private function countRows(string $from, array $whereConditions, array $whereParams): int
    {
        $result = qb()->select(['COUNT(*) as cnt'])
            ->from($from)
            ->where($whereConditions)
            ->params($whereParams)
            ->execute()
            ->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['cnt'] ?? 0);
    }
}
