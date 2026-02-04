<?php

use Echo\Interface\Database\Migration;

return new class implements Migration
{
    public function up(): string
    {
        return "INSERT INTO modules (link, title, icon, item_order, parent_id) VALUES
            ('audits', 'Audits', 'journal-text', 50, 3)";
    }

    public function down(): string
    {
        return "DELETE FROM modules WHERE link = 'audits'";
    }
};
