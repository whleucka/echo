<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    private string $table = "modules";

    public function up(): string
    {
         return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->unsignedTinyInteger("enabled")->default(1);
            $table->varchar("link")->nullable();
            $table->varchar("title");
            $table->varchar("icon")->nullable();
            $table->unsignedSmallInteger("item_order")->default(0);
            $table->unsignedBigInteger("parent_id")->nullable();
            $table->timestamps();
            $table->unique("title");
            $table->primaryKey("id");
            $table->foreignKey("parent_id")->references("modules", "id")->onDelete("SET NULL");
        });
    }

    public function down(): string
    {
         return Schema::drop($this->table);
    }
};
