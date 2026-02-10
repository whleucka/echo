<?php

use Echo\Framework\Database\MigrationInterface;

return new class implements MigrationInterface
{
    public function up(): string
    {
        return "INSERT INTO modules (link, title, icon, item_order, parent_id) VALUES 
            (null, 'Overview', null, 10, null),
            (null, 'Management', null, 20, null),
            (null, 'Monitoring', null, 30, null),
            ('dashboard', 'Dashboard', 'speedometer2', 0, 1),
            ('modules', 'Modules', 'puzzle', 10, 2),
            ('users', 'Users', 'people', 20, 2),
            ('user-permissions', 'User Permissions', 'shield-lock', 30, 2),
            ('activity', 'Activity', 'activity', 40, 3)";
    }

    public function down(): string
    {
        // Fixme: ref integrety breakage
        return "DELETE FROM modules";
    }
};
