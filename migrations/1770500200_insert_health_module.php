<?php

use Echo\Interface\Database\Migration;

return new class implements Migration
{
    public function up(): string
    {
        return "INSERT INTO modules (link, title, icon, item_order, parent_id) VALUES
            ('health', 'System Health', 'heart-pulse', 60, 3)";
    }

    public function down(): string
    {
        return "DELETE FROM modules WHERE link = 'health'";
    }
};
