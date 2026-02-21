<?php

namespace Echo\Framework\Admin;

use Echo\Framework\Admin\Schema\ColumnDefinition;
use RuntimeException;

class CsvExporter
{
    public function __construct(
        private array $columns,
        private \Closure $exportOverride,
    ) {}

    public function stream(iterable $rows, string $filename = 'export.csv'): void
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output_handle = fopen('php://output', 'w');
        if ($output_handle === false) {
            throw new RuntimeException("Unable to open output stream");
        }

        // Write headers from schema columns
        $headers = array_map(fn(ColumnDefinition $col) => $col->label, $this->columns);
        fputcsv($output_handle, $headers, escape: '');

        foreach ($rows as $row) {
            $row = ($this->exportOverride)($row);
            $ordered_row = [];
            foreach ($this->columns as $col) {
                $ordered_row[] = $this->sanitizeCsvValue($row[$col->name] ?? '');
            }
            fputcsv($output_handle, $ordered_row, escape: '');
            flush();
        }

        fclose($output_handle);
        exit;
    }

    private function sanitizeCsvValue(mixed $value): string
    {
        $value = (string) $value;
        if (preg_match('/^[=+\-@\t\r]/', $value)) {
            return "'" . $value;
        }
        return $value;
    }
}
