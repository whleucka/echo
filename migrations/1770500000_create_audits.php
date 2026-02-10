<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface
{
    private string $table = "audits";

    public function up(): string
    {
        return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger("user_id")->nullable();
            $table->varchar("auditable_type");
            $table->unsignedBigInteger("auditable_id");
            $table->varchar("event", 50);
            $table->json("old_values")->nullable();
            $table->json("new_values")->nullable();
            $table->varchar("ip_address", 45)->nullable();
            $table->text("user_agent")->nullable();
            $table->timestamps();
            $table->primaryKey("id");
            $table->index(["auditable_type", "auditable_id"]);
            $table->index(["user_id"]);
            $table->index(["created_at"]);
            $table->foreignKey("user_id")
                ->references("users", "id")
                ->onDelete("SET NULL");
        });
    }

    public function down(): string
    {
        return Schema::drop($this->table);
    }
};
