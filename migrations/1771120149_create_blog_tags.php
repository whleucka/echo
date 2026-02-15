<?php

use Echo\Framework\Database\{Schema, Blueprint, MigrationInterface};

return new class implements MigrationInterface
{
    private string $table = "blog_tags";

    public function up(): string
    {
         return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->varchar("name");
            $table->varchar("slug");
            $table->unique("slug");
            $table->primaryKey("id");
        });
    }

    public function down(): string
    {
         return Schema::drop($this->table);
    }
};
