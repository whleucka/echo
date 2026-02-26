<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    public function up(): string
    {
        return Schema::alter('users', function (Blueprint $table) {
            $table->varchar('theme')->default("'light'");
        });
    }

    public function down(): string
    {
        return Schema::alter('users', function (Blueprint $table) {
            $table->dropColumn('theme');
        });
    }
};
