<?php

namespace Echo\Framework\Http;

use App\Models\FileInfo;
use RuntimeException;

class FileUploadHandler
{
    /**
     * Default MIME types allowed for upload.
     * Override via config('security.allowed_upload_mimes').
     */
    private const DEFAULT_ALLOWED_MIMES = [
        // Images
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml',
        // Documents
        'application/pdf',
        'text/plain', 'text/csv',
        // Office
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        // Archives
        'application/zip',
    ];

    public function handle(array $file): int|false
    {
        $upload_dir = config("paths.uploads");
        if (!is_dir($upload_dir)) {
            $result = mkdir($upload_dir, 0775, true);
            if (!$result) {
                throw new RuntimeException("Cannot create uploads directory" . $file['error']);
            }
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException("File upload error: " . $file['error']);
        }

        $og_name = basename($file['name']);
        $extension = pathinfo($og_name, PATHINFO_EXTENSION);
        $unique_name = uniqid('file_', true) . ($extension ? ".$extension" : "");
        $target_path = sprintf("%s/%s", $upload_dir, $unique_name);

        if (!move_uploaded_file($file['tmp_name'], $target_path)) {
            throw new RuntimeException("Failed to move uploaded file.");
        }

        $mime_type = mime_content_type($target_path);

        // Validate MIME type against allowlist
        $allowed = config('security.allowed_upload_mimes') ?? self::DEFAULT_ALLOWED_MIMES;
        if (!in_array($mime_type, $allowed, true)) {
            unlink($target_path);
            throw new RuntimeException("File type not allowed: $mime_type");
        }

        $file_size = filesize($target_path);
        $relative_path = sprintf("/uploads/%s", $unique_name);

        $result = FileInfo::create([
            "original_name" => $og_name,
            "stored_name" => $unique_name,
            "path" => $relative_path,
            "mime_type" => $mime_type,
            "size" => $file_size,
        ]);

        return $result->id ?? false;
    }
}
