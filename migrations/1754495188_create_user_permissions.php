<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    private string $table = "user_permissions";

    public function up(): string
    {
         return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("module_id");
            $table->unsignedBigInteger("user_id");
            $table->unsignedTinyInteger("has_create")->default(0);
            $table->unsignedTinyInteger("has_edit")->default(0);
            $table->unsignedTinyInteger("has_delete")->default(0);
            $table->unsignedTinyInteger("has_export")->default(0);
            $table->timestamps();
            $table->primaryKey("id");
            $table->unique("module_id, user_id");
            $table->foreignKey("module_id")->references("modules", "id")->onDelete("CASCADE");
            $table->foreignKey("user_id")->references("users", "id")->onDelete("CASCADE");
        });
    }

    public function down(): string
    {
         return Schema::drop($this->table);
    }
};
