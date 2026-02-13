<?php

namespace App\Http\Controllers\Admin;

use App\Models\BlogPost;
use Echo\Framework\Admin\Schema\{FormSchemaBuilder, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/blog-posts", namePrefix: "blog-posts")]
class BlogPostsController extends ModuleController
{
    protected string $tableName = "blog_posts";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
    }
}
