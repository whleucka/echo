<?php

namespace Echo\Framework\Admin\Schema;

final class PaginationConfig
{
    public function __construct(
        public readonly int $perPage = 10,
        public readonly array $perPageOptions = [10, 25, 50, 100],
        public readonly int $paginationLinks = 2,
    ) {}
}
