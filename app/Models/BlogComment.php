<?php

namespace App\Models;

use Echo\Framework\Audit\Auditable;
use Echo\Framework\Database\Model;

class BlogComment extends Model
{
    use Auditable;

    protected string $tableName = "blog_comments";

    public function blogPost()
    {
        return $this->belongsTo(BlogPost::class, "blog_post_id");
    }
}
