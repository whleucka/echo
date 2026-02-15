<?php

use Echo\Framework\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return "INSERT INTO modules (id, link, title, icon, item_order, parent_id) VALUES 
            (15, 'blog/comments', 'Blog Comments', 'chat-dots', 1, 11)";
    }

    public function down(): string
    {
        return "DELETE FROM modules WHERE id = 15";
    }
};
