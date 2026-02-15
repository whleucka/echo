<?php

namespace App\Http\Controllers\Blog;

use App\Services\BlogService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Post;

/**
 * Public Blog Controller
 *
 * Provides public endpoints for blog interaction.
 * Comments are created with 'pending' status for moderation.
 */
#[Group(pathPrefix: "/blog", namePrefix: "blog", middleware: ["api"])]
class BlogController extends Controller
{
    public function __construct(private BlogService $service)
    {
    }

    /**
     * Submit a comment on a blog post
     *
     * POST /blog/{slug}/comment
     *
     * Expects JSON body:
     * {
     *   "author_name": "string",
     *   "author_email": "string",
     *   "content": "string"
     * }
     *
     * @param string $slug The blog post slug
     * @return array JSON response
     */
    #[Post("/{slug}/comment", "blog.comment")]
    public function comment(string $slug): array
    {
        header('Content-Type: application/json');

        $post = $this->service->findPostBySlug($slug);
        if (!$post) {
            http_response_code(404);
            return [
                'success' => false,
                'error' => 'Blog post not found',
            ];
        }

        $input = json_decode(file_get_contents('php://input'), true);
        if (!$input) {
            http_response_code(400);
            return [
                'success' => false,
                'error' => 'Invalid JSON input',
            ];
        }

        $errors = $this->service->validateCommentInput($input);
        if (!empty($errors)) {
            http_response_code(422);
            return [
                'success' => false,
                'errors' => $errors,
            ];
        }

        $comment = $this->service->createComment(
            $post,
            trim($input['author_name']),
            trim($input['author_email']),
            trim($input['content'])
        );

        if ($comment) {
            http_response_code(201);
            return [
                'success' => true,
                'message' => 'Comment submitted successfully. It will be visible after moderation.',
                'comment_id' => $comment->id,
            ];
        }

        http_response_code(500);
        return [
            'success' => false,
            'error' => 'Failed to create comment',
        ];
    }
}
