<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\PasswordResetService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Http\Response;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};

#[Group(pathPrefix: '/admin')]
class PasswordResetController extends Controller
{
    public function __construct(private PasswordResetService $service)
    {
    }

    #[Get("/password-reset", "auth.password-reset.index")]
    public function index(): string|Response
    {
        $token = request()->get->get('token', '');
        $email = request()->get->get('email', '');

        if (!$token || !$email) {
            return redirect(uri('auth.forgot-password.index'))
                ->withFlash("warning", "Invalid password reset link");
        }

        $user = $this->service->validateToken($email, $token);

        if (!$user) {
            return redirect(uri('auth.forgot-password.index'))
                ->withFlash("warning", "This password reset link is invalid or has expired");
        }

        return $this->render("auth/password-reset/index.html.twig", [
            'token' => $token,
            'email' => $email,
        ]);
    }

    #[Post("/password-reset", "auth.password-reset.post", ["max_requests" => 5, "decay_seconds" => 60])]
    public function post(): string|Response
    {
        $token = request()->post->get('token', '');
        $email = request()->post->get('email', '');

        $this->setValidationMessage("password.min_length", "Must be at least 10 characters");
        $this->setValidationMessage("password.regex", "Must contain 1 upper case, 1 digit, 1 symbol");
        $this->setValidationMessage("password_match.match", "Password does not match");

        $valid = $this->validate([
            "password" => ["required", "min_length:10", "regex:^(?=.*[A-Z])(?=.*\W)(?=.*\d).+$"],
            "password_match" => ["required", "match:password"],
        ]);

        if (!$valid) {
            return $this->render("auth/password-reset/index.html.twig", [
                'token' => $token,
                'email' => $email,
            ]);
        }

        $user = $this->service->validateToken($email, $token);

        if (!$user) {
            return redirect(uri('auth.forgot-password.index'))
                ->withFlash("warning", "This password reset link is invalid or has expired");
        }

        $this->service->resetPassword($user, $valid->password);

        return redirect(uri('auth.sign-in.index'))
            ->withFlash("success", "Your password has been reset. Please sign in with your new password");
    }
}
