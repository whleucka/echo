<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Admin\Schema\{FormSchemaBuilder, ModalSize, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Post;

#[Group(pathPrefix: "/blog/posts", namePrefix: "blog.posts")]
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

        $builder->field('user_id', 'Author')
                ->dropdown()
                ->optionsFrom("SELECT id as value, CONCAT(first_name, ' ', surname) as label
                    FROM users
                    ORDER BY label")
                ->default(user()->id);
        $builder->field('cover', 'Cover')
                ->image()
                ->accept('image/*');
        $builder->field('status', 'Status')
                ->dropdown()
                ->options([
                    ['value' => 'draft', 'label' => 'Draft'],
                    ['value' => 'published', 'label' => 'Published'],
                    ['value' => 'archived', 'label' => 'Archived'],
                ])
                ->default('draft');
        $builder->field('slug', 'URL Slug')
                ->input()
                ->rules(['required']);
        $builder->field('title', 'Title')
                ->input()
                ->rules(['required']);
        $builder->field('subtitle', 'Subtitle')
                ->input();
        $builder->field('tags', 'Tags')
                ->multiselect()
                ->optionsFrom("SELECT id as value, name as label 
                    FROM blog_tags 
                    ORDER BY name")
                ->pivot('blog_post_tags', 'blog_post_id', 'blog_tag_id');
        $builder->field('content', 'Content')
                ->editor();
    }

    #[Post("/upload-image", "blog.posts.upload-image")]
    public function uploadImage(): void
    {
        $file = $this->request->files->image ?? null;
        if (!$file) {
            echo json_encode(['success' => 0, 'message' => 'No file uploaded']);
            exit;
        }

        try {
            $fileInfoId = $this->handleFileUpload($file);
            if ($fileInfoId) {
                $fileInfo = db()->fetch(
                    "SELECT path FROM file_info WHERE id = ?",
                    [$fileInfoId]
                );
                echo json_encode([
                    'success' => 1,
                    'file' => ['url' => $fileInfo['path'] ?? '']
                ]);
            } else {
                echo json_encode(['success' => 0, 'message' => 'Upload failed']);
            }
        } catch (\Throwable $e) {
            error_log($e->getMessage());
            echo json_encode(['success' => 0, 'message' => 'Upload error']);
        }
        exit;
    }
}
