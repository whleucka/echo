<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    public function up(): string
    {
        // users: UUID lookups on every request
        return Schema::alter('users', function (Blueprint $table) {
            $table->addIndex('idx_uuid', ['uuid']);
        });
    }

    public function down(): string
    {
        return Schema::alter('users', function (Blueprint $table) {
            $table->dropIndex('idx_uuid');
        });
    }
};
