<?php

namespace App\Http\Controllers\Admin;

use App\Models\BlogComment;
use Echo\Framework\Admin\Schema\{FormSchemaBuilder, ModalSize, TableSchemaBuilder};
use Echo\Framework\Http\ModuleController;
use Echo\Framework\Routing\Group;
use Echo\Framework\Session\Flash;

#[Group(pathPrefix: "/blog/comments", namePrefix: "blog.comments")]
class BlogCommentsController extends ModuleController
{
    protected string $tableName = "blog_comments";

    protected function defineTable(TableSchemaBuilder $builder): void
    {
        $builder->join('LEFT JOIN blog_posts ON blog_posts.id = blog_comments.blog_post_id');
        $builder->primaryKey('blog_comments.id');

        $builder->column('id', 'ID', 'blog_comments.id');
        $builder->column('status', 'Status', 'blog_comments.status');
        $builder->column('author_name', 'Author')->searchable();
        $builder->column('author_email', 'Email')->searchable();
        $builder->column('post_title', 'Post', 'blog_posts.title');
        $builder->column('created_at', 'Created', 'blog_comments.created_at');

        $builder->defaultSort('created_at', 'DESC');
        $builder->dateColumn('blog_comments.created_at');

        $builder->filter('status', 'blog_comments.status')->label('Status')
                ->options([
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'denied', 'label' => 'Denied'],
                ]);

        $builder->filterLink('Pending', "blog_comments.status = 'pending'");
        $builder->filterLink('Approved', "blog_comments.status = 'approved'");
        $builder->filterLink('Denied', "blog_comments.status = 'denied'");

        $builder->rowAction('show');
        $builder->rowAction('approve')->label('Approve')->icon('bi-check-circle')->requiresForm(false);
        $builder->rowAction('deny')->label('Deny')->icon('bi-x-circle')->requiresForm(false);
        $builder->rowAction('delete');

        $builder->toolbarAction('export');

        $builder->bulkAction('approve', 'Approve');
        $builder->bulkAction('deny', 'Deny');
        $builder->bulkAction('delete', 'Delete');
    }

    protected function defineForm(FormSchemaBuilder $builder): void
    {
        $builder->modalSize(ModalSize::Large);

        $builder->field('author_name', 'Author Name')
                ->input()
                ->readonly();
        $builder->field('author_email', 'Author Email')
                ->email()
                ->readonly();
        $builder->field('status', 'Status')
                ->dropdown()
                ->options([
                    ['value' => 'pending', 'label' => 'Pending'],
                    ['value' => 'approved', 'label' => 'Approved'],
                    ['value' => 'denied', 'label' => 'Denied'],
                ])
                ->readonly();
        $builder->field('content', 'Comment')
                ->textarea()
                ->readonly();
    }

    protected function handleTableAction(int $id, string $action)
    {
        $exec = match ($action) {
            "approve" => function ($id) {
                $comment = BlogComment::find($id);
                if ($comment) {
                    $comment->update(['status' => 'approved']);
                    Flash::add("success", "Comment #$id approved");
                }
            },
            "deny" => function ($id) {
                $comment = BlogComment::find($id);
                if ($comment) {
                    $comment->update(['status' => 'denied']);
                    Flash::add("success", "Comment #$id denied");
                }
            },
            "delete" => function ($id) {
                if ($this->hasDelete($id)) {
                    $result = $this->handleDestroy($id);
                    if ($result) {
                        Flash::add("success", "Successfully deleted record");
                    }
                } else {
                    Flash::add("warning", "Cannot delete record $id");
                }
            },
            default => fn() => Flash::add("warning", "Unknown action"),
        };
        if (is_callable($exec)) {
            $exec($id);
        }
    }
}
