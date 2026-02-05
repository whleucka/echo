<?php

use Echo\Interface\Database\Migration;
use Echo\Framework\Database\{Schema, Blueprint};

return new class implements Migration
{
    public function up(): string
    {
        // api_tokens: Used in bearer auth validation
        return Schema::alter('api_tokens', function (Blueprint $table) {
            $table->addIndex('idx_token_revoked', ['token', 'revoked']);
        });
    }

    public function down(): string
    {
        return Schema::alter('api_tokens', function (Blueprint $table) {
            $table->dropIndex('idx_token_revoked');
        });
    }
};
