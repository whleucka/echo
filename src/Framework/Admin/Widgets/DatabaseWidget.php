<?php

namespace Echo\Framework\Admin\Widgets;

use Echo\Framework\Admin\Widget;

class DatabaseWidget extends Widget
{
    protected string $id = 'database';
    protected string $title = 'Database Stats';
    protected string $icon = 'database';
    protected string $template = 'admin/widgets/database.html.twig';
    protected int $width = 6;
    protected int $refreshInterval = 120;
    protected int $cacheTtl = 60;
    protected int $priority = 50;

    public function getData(): array
    {
        $tables = db()->fetchAll(
            "SELECT
                table_name,
                table_rows,
                ROUND(data_length / 1024 / 1024, 2) AS data_size_mb,
                ROUND(index_length / 1024 / 1024, 2) AS index_size_mb
            FROM information_schema.tables
            WHERE table_schema = DATABASE()
            ORDER BY table_rows DESC"
        );

        $totalRows = 0;
        $totalSize = 0;
        $tableCount = count($tables);

        foreach ($tables as $table) {
            $totalRows += (int)$table['table_rows'];
            $totalSize += (float)$table['data_size_mb'] + (float)$table['index_size_mb'];
        }

        return [
            'table_count' => $tableCount,
            'total_rows' => number_format($totalRows),
            'total_size' => number_format($totalSize, 2) . ' MB',
            'tables' => array_slice($tables, 0, 10),
        ];
    }
}
