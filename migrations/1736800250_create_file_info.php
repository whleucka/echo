<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    private string $table = "file_info";

    public function up(): string
    {
         return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->varchar("original_name");
            $table->varchar("stored_name");
            $table->varchar("path");
            $table->varchar("mime_type");
            $table->unsignedBigInteger("size");
            $table->timestamps();
            $table->primaryKey("id");
        });
    }

    public function down(): string
    {
         return Schema::drop($this->table);
    }
};
