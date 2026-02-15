<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\{FormSchemaBuilder, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/blog/tags", namePrefix: "blog.tags")]
class BlogTagsController extends ModuleController
{
    protected string $tableName = "blog_tags";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->column('id', 'ID');
        $builder->column('name', 'Name')->searchable();
        $builder->column('slug', 'Slug');
        $builder->column('created_at', 'Created');

        $builder->rowAction('show');
        $builder->rowAction('edit');
        $builder->rowAction('delete');

        $builder->toolbarAction('create');
        $builder->toolbarAction('export');

        $builder->bulkAction('delete', 'Delete');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->field('name', 'Name')
                ->input()
                ->rules(['required']);
        $builder->field('slug', 'URL Slug')
                ->input()
                ->rules(['required']);
    }
}
