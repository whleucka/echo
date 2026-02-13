<?php

use Echo\Framework\Database\{Schema, Blueprint, MigrationInterface};

return new class implements MigrationInterface
{
    private string $table = "blog_posts";

    public function up(): string
    {
         return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger("cover")->nullable();
            $table->enum("status", ["draft", "published", "archived"]);
            $table->varchar("slug");
            $table->unsignedBigInteger("user_id");
            $table->varchar("title");
            $table->varchar("subtitle");
            $table->longText("content")->nullable();
            $table->unique("slug");
            $table->index(["slug"]);
            $table->timestamp("publish_at")->nullable();
            $table->primaryKey("id");
            $table->foreignKey("user_id")->references("users", "id");
        });
    }

    public function down(): string
    {
         return Schema::drop($this->table);
    }
};
