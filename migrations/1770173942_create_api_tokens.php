<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    private string $table = "api_tokens";

    public function up(): string
    {
        return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->varchar('name', 255);
            $table->char('token', 64);  // SHA256 hash
            $table->timestamp('expires_at', 0)->nullable();
            $table->timestamp('last_used_at', 0)->nullable();
            $table->boolean('revoked')->default('0');
            $table->timestamps();
            $table->primaryKey('id');
            $table->foreignKey('user_id')->references('users', 'id')->onDelete('CASCADE');
            $table->unique('token');
            $table->index(['user_id']);
        });
    }

    public function down(): string
    {
        return Schema::drop($this->table);
    }
};
