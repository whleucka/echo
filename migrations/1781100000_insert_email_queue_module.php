<?php

use Echo\Framework\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return "INSERT INTO modules (link, title, icon, item_order, parent_id) VALUES
            ('email-queue', 'Email Queue', 'envelope', 45, 3)";
    }

    public function down(): string
    {
        return "DELETE FROM modules WHERE link = 'email-queue'";
    }
};
