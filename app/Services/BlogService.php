<?php

namespace App\Services;

use App\Models\BlogComment;
use App\Models\BlogPost;

class BlogService
{
    /**
     * Find a blog post by slug
     */
    public function findPostBySlug(string $slug): ?BlogPost
    {
        return BlogPost::where('slug', $slug)->first();
    }

    /**
     * Create a comment on a blog post
     *
     * @param BlogPost $post The blog post to comment on
     * @param string $authorName Comment author's name
     * @param string $authorEmail Comment author's email
     * @param string $content Comment content
     * @return BlogComment|null The created comment or null on failure
     */
    public function createComment(
        BlogPost $post,
        string $authorName,
        string $authorEmail,
        string $content
    ): ?BlogComment {
        return BlogComment::create([
            'blog_post_id' => $post->id,
            'author_name' => $authorName,
            'author_email' => $authorEmail,
            'content' => $content,
            'status' => 'pending',
        ]);
    }

    /**
     * Validate comment input data
     *
     * @param array $input The input data to validate
     * @return array Array of validation errors (empty if valid)
     */
    public function validateCommentInput(array $input): array
    {
        $errors = [];

        $authorName = trim($input['author_name'] ?? '');
        $authorEmail = trim($input['author_email'] ?? '');
        $content = trim($input['content'] ?? '');

        if (empty($authorName)) {
            $errors['author_name'] = 'Author name is required';
        }

        if (empty($authorEmail)) {
            $errors['author_email'] = 'Author email is required';
        } elseif (!filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            $errors['author_email'] = 'Invalid email format';
        }

        if (empty($content)) {
            $errors['content'] = 'Comment content is required';
        }

        return $errors;
    }
}
