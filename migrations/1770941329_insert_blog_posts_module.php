<?php

use Echo\Framework\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return "INSERT INTO modules (id, link, title, icon, item_order, parent_id) VALUES 
            (11, null, 'Blog', null, 40, null),
            (12, 'blog/posts', 'Blog Posts', 'pencil', 0, 11)";
    }

    public function down(): string
    {
        return "DELETE FROM modules WHERE link = 'blog-posts'";
    }
};
