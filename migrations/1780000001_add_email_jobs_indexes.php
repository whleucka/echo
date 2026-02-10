<?php

use Echo\Framework\Database\MigrationInterface;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements MigrationInterface {
    public function up(): string
    {
        return Schema::alter('email_jobs', function (Blueprint $table) {
            $table->addIndex('idx_email_jobs_status_scheduled', ['status', 'scheduled_at']);
            $table->addIndex('idx_email_jobs_status_attempts', ['status', 'attempts']);
        });
    }

    public function down(): string
    {
        return Schema::alter('email_jobs', function (Blueprint $table) {
            $table->dropIndex('idx_email_jobs_status_scheduled');
            $table->dropIndex('idx_email_jobs_status_attempts');
        });
    }
};
