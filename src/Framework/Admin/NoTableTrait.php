<?php

namespace Echo\Framework\Admin;
use Echo\Framework\Admin\Schema\TableSchemaBuilder;

trait NoTableTrait
{
    protected function defineTable(TableSchemaBuilder $builder): void
    {
        // No table
    }
}
