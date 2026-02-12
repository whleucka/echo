<?php

namespace Echo\Framework\Admin;

use Echo\Framework\Admin\Schema\TableSchema;

interface TableDataSource
{
    /**
     * Fetch paginated rows for a table.
     *
     * @param TableSchema $schema          The table schema definition
     * @param int         $page            Current page number
     * @param int         $perPage         Rows per page
     * @param string      $orderBy         Column to sort by
     * @param string      $sort            Sort direction (ASC/DESC)
     * @param array       $whereConditions SQL WHERE clauses
     * @param array       $whereParams     Bound parameter values
     */
    public function fetch(
        TableSchema $schema,
        int $page,
        int $perPage,
        array $perPageOptions,
        string $orderBy,
        string $sort,
        array $whereConditions,
        array $whereParams,
    ): TableResult;
}
