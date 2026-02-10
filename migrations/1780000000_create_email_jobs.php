<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface {
    private string $table = "email_jobs";

    public function up(): string
    {
        return Schema::create($this->table, function (Blueprint $table) {
            $table->id();
            $table->varchar("to_address", 500);
            $table->varchar("subject", 500);
            $table->longText("payload");
            $table->enum("status", ["pending", "processing", "sent", "failed", "exhausted"])->default("'pending'");
            $table->unsignedTinyInteger("attempts")->default("0");
            $table->unsignedTinyInteger("max_attempts")->default("3");
            $table->varchar("error_message", 1000)->nullable();
            $table->timestamp("scheduled_at")->nullable();
            $table->timestamp("last_attempt_at")->nullable();
            $table->timestamp("sent_at")->nullable();
            $table->timestamps();
            $table->primaryKey("id");
        });
    }

    public function down(): string
    {
        return Schema::drop($this->table);
    }
};
