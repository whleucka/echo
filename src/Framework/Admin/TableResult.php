<?php

namespace Echo\Framework\Admin;

final class TableResult
{
    public function __construct(
        public readonly array $rows,
        public readonly int $totalRows,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $totalPages,
    ) {}
}
