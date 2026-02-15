<?php

use Echo\Framework\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return "INSERT INTO modules (id, link, title, icon, item_order, parent_id) VALUES 
            (13, 'blog/tags', 'Blog Tags', 'tag', 1, 11)";
    }

    public function down(): string
    {
        return "DELETE FROM modules WHERE link = 'blog/tags'";
    }
};
