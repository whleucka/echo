<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    public function up(): string
    {
        return Schema::alter('activity', function (Blueprint $table) {
            $table->smallInt('status_code')->unsigned()->nullable();
            $table->addIndex('idx_status_code', ['status_code']);
        });
    }

    public function down(): string
    {
        return Schema::alter('activity', function (Blueprint $table) {
            $table->dropIndex('idx_status_code');
            $table->dropColumn('status_code');
        });
    }
};
