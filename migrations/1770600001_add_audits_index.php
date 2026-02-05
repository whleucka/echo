<?php

use Echo\Interface\Database\Migration;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements Migration
{
    public function up(): string
    {
        // audits: Used in activity feeds and queries
        return Schema::alter('audits', function (Blueprint $table) {
            $table->addIndex('idx_created_at', ['created_at']);
        });
    }

    public function down(): string
    {
        return Schema::alter('audits', function (Blueprint $table) {
            $table->dropIndex('idx_created_at');
        });
    }
};
