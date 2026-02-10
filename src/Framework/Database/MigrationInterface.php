<?php

namespace Echo\Framework\Database;

interface MigrationInterface
{
    public function up(): string;
    public function down(): string;
}
