<?php

use Echo\Framework\Database\{Schema, Blueprint, MigrationInterface};

return new class implements MigrationInterface
{
    private string $table = "blog_post_tags";

    public function up(): string
    {
         return Schema::create($this->table, function (Blueprint $table) {
            $table->unsignedBigInteger("blog_post_id");
            $table->unsignedBigInteger("blog_tag_id");
            $table->primaryKey("blog_post_id, blog_tag_id");
            $table->foreignKey("blog_post_id")->references("blog_posts", "id")->onDelete("CASCADE");
            $table->foreignKey("blog_tag_id")->references("blog_tags", "id")->onDelete("CASCADE");
        });
    }

    public function down(): string
    {
         return Schema::drop($this->table);
    }
};
