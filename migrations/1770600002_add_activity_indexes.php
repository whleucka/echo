<?php

use Echo\Interface\Database\Migration;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements Migration
{
    public function up(): string
    {
        // sessions: High-frequency inserts and queries
        return Schema::alter('activity', function (Blueprint $table) {
            $table->addIndex('idx_user_id', ['user_id']);
            $table->addIndex('idx_created_at', ['created_at']);
        });
    }

    public function down(): string
    {
        return Schema::alter('activity', function (Blueprint $table) {
            $table->dropIndex('idx_user_id');
            $table->dropIndex('idx_created_at');
        });
    }
};
