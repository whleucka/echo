<?php

use Echo\Framework\Database\{Schema, Blueprint, MigrationInterface};

return new class implements MigrationInterface
{
    private string $table = "blog_comments";

    public function up(): string
    {
         return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->timestamps();
            $table->unsignedBigInteger("blog_post_id");
            $table->varchar("author_name");
            $table->varchar("author_email");
            $table->text("content");
            $table->enum("status", ["pending", "approved", "denied"])->default("'pending'");
            $table->primaryKey("id");
            $table->foreignKey("blog_post_id")->references("blog_posts", "id")->onDelete("CASCADE");
        });
    }

    public function down(): string
    {
         return Schema::drop($this->table);
    }
};
