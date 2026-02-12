<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\ForgotPasswordService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Session\Flash;

#[Group(pathPrefix: '/admin')]
class ForgotPasswordController extends Controller
{
    public function __construct(private ForgotPasswordService $service)
    {
    }

    #[Get("/forgot-password", "auth.forgot-password.index")]
    public function index(): string
    {
        return $this->render("auth/forgot-password/index.html.twig");
    }

    #[Post("/forgot-password", "auth.forgot-password.post", ["max_requests" => 5, "decay_seconds" => 60])]
    public function post(): string
    {
        $valid = $this->validate([
            "email" => ["required", "email"],
        ]);

        if ($valid) {
            $this->service->requestReset($valid->email);
        }

        Flash::add("success", "If an account exists with that email, a password reset link has been sent");

        return $this->index();
    }
}
