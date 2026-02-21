<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    public function up(): string
    {
        return Schema::alter('activity', function (Blueprint $table) {
            $table->char('country_code', 2)->nullable();
            $table->addIndex('idx_country_code', ['country_code']);
        });
    }

    public function down(): string
    {
        return Schema::alter('activity', function (Blueprint $table) {
            $table->dropIndex('idx_country_code');
            $table->dropColumn('country_code');
        });
    }
};
