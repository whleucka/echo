<?php

namespace App\Models;

use Echo\Framework\Audit\Auditable;
use Echo\Framework\Database\Model;

class FileInfo extends Model
{
    use Auditable;

    public function __construct(?string $id = null)
    {
        parent::__construct('file_info', $id);
    }

    public function delete(): bool
    {
        $upload_dir = config("paths.uploads");
        $file_path = sprintf("%s/%s", $upload_dir, $this->stored_name);
        $result = parent::delete();
        if ($result && file_exists($file_path)) {
            // Delete the uploaded file
            unlink($file_path);
        }
        return $result;
    }
}
