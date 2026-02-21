<?php

namespace Echo\Framework\Http;

use App\Models\FileInfo;
use RuntimeException;

class FileUploadHandler
{
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
