<?php

namespace App\Http\Controllers\Admin;

use App\Models\FileInfo;
use Echo\Framework\Admin\Schema\{FormSchemaBuilder, ModalSize, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/file-info", namePrefix: "file-info")]
class FileInfoController extends ModuleController
{
    protected string $tableName = "file_info";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->defaultSort('id', 'DESC');

        $builder->column('id', 'ID');
        $builder->column('original_name', 'Original Name')
            ->searchable();
        $builder->column('stored_name', 'Stored Name');
        $builder->column('mime_type', 'MIME Type')
            ->searchable();
        $builder->column('size', 'Size')
            ->formatUsing(fn($col, $val) => format_bytes((int) $val));
        $builder->column('created_at', 'Uploaded');

        $builder->filter('mime_type', 'mime_type')
            ->label('File Type')
            ->optionsFrom("SELECT DISTINCT mime_type as value, mime_type as label FROM file_info ORDER BY mime_type");

        $builder->dateColumn('created_at');

        // Show only - no edit/create
        $builder->rowAction('show');
        $builder->rowAction('delete');

        $builder->toolbarAction('export');

        $builder->bulkAction('delete', 'Delete');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->modalSize(ModalSize::Large);

        // Image preview (only shown for image files)
        $builder->field('preview', 'Preview', "CONCAT(path, '|', mime_type) as preview")
            ->renderUsing(fn($col, $val) => $this->renderPreview($val));

        // Read-only fields for show view
        $builder->field('original_name', 'Original Name')
            ->input()
            ->readonly();

        $builder->field('stored_name', 'Stored Name')
            ->input()
            ->readonly();

        $builder->field('path', 'Path')
            ->input()
            ->readonly();

        $builder->field('mime_type', 'MIME Type')
            ->input()
            ->readonly();

        $builder->field('size', 'File Size')
            ->input()
            ->readonly();

        $builder->field('created_at', 'Uploaded At')
            ->input()
            ->readonly();

        $builder->field('updated_at', 'Updated At')
            ->input()
            ->readonly();
    }

    /**
     * Render image preview if file is an image.
     */
    private function renderPreview(?string $value): string
    {
        if (!$value) {
            return '<span class="text-muted">No preview available</span>';
        }

        // Value is "path|mime_type"
        $parts = explode('|', $value, 2);
        $path = $parts[0] ?? '';
        $mimeType = $parts[1] ?? '';

        if (!$path || !str_starts_with($mimeType, 'image/')) {
            return '<span class="text-muted">No preview available</span>';
        }

        return sprintf(
            '<div class="text-center"><img src="%s" alt="Preview" class="img-fluid rounded" style="max-height: 300px;"></div>',
            htmlspecialchars($path)
        );
    }

    /**
     * Override form data to format file size for display.
     */
    protected function formOverride(?int $id, array $form): array
    {
        if (isset($form['size'])) {
            $form['size'] = format_bytes((int) $form['size']);
        }
        return $form;
    }

    /**
     * Override destroy to use FileInfo model which handles file deletion.
     */
    protected function handleDestroy(int $id): bool
    {
        $fileInfo = new FileInfo($id);
        if ($fileInfo->id) {
            return $fileInfo->delete();
        }
        return false;
    }

    /**
     * Format row data for table display.
     */
    protected function formatRow(array $row): array
    {
        return $row;
    }

    /**
     * Override export to show raw size in bytes.
     */
    protected function exportOverride(array $row): array
    {
        // Keep size as bytes for export
        return $row;
    }
}
