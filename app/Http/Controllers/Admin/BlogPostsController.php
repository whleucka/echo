<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\{FormSchemaBuilder, ModalSize, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;

#[Group(pathPrefix: "/blog-posts", namePrefix: "blog-posts")]
class BlogPostsController extends ModuleController
{
    protected string $tableName = "blog_posts";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->column('id', 'ID');
        $builder->column('status', 'Status');
        $builder->column('title', 'Title')->searchable();
        $builder->column('published_at', 'Published Date');
        $builder->column('created_at', 'Created');

        $builder->filter('status', 'status')->label('Status')
                ->options([
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'published', 'label' => 'Published'],
                    ['value' => 'archived', 'label' => 'Archived'],
                ]);

        $builder->filterLink('Draft', "status = 'draft'");
        $builder->filterLink('Published', "status = 'published'");
        $builder->filterLink('Archived', "status = 'archived'");

        $builder->rowAction('show');
        $builder->rowAction('edit');
        $builder->rowAction('delete');

        $builder->toolbarAction('create');
        $builder->toolbarAction('export');

        $builder->bulkAction('delete', 'Delete');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->modalSize(ModalSize::ExtraLarge);

        $builder->field('cover', 'Cover')
                ->image()
                ->accept('image/*');
        $builder->field('status', 'Status')
                ->dropdown()
                ->options([
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'published', 'label' => 'Published'],
                    ['value' => 'archived', 'label' => 'Archived'],
                ]);
        $builder->field('slug', 'URL Slug')
                ->input()
                ->rules(['required']);
        $builder->field('title', 'Title')
                ->input()
                ->rules(['required']);
        $builder->field('subtitle', 'Subtitle')
                ->input();
        $builder->field('content', 'Content')
                ->textarea();
    }
}
