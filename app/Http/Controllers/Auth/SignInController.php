<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\SignInService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Session\Flash;

#[Group(path_prefix: '/admin')]
class SignInController extends Controller
{
    public function __construct(private SignInService $service)
    {
    }

    #[Get("/sign-in", "auth.sign-in.index")]
    public function index(): string
    {
        return $this->render("auth/sign-in/index.html.twig", [
            "register_enabled" => config("security.register_enabled")
        ]);
    }

    #[Post("/sign-in", "auth.sign-in.post", ["max_requests" => 20])]
    public function post(): string
    {
        $valid = $this->validate([
            "email" => ["required", "email"],
            "password" => ["required"],
        ]);
        if ($valid) {
            $success = $this->service->signIn($valid->email, $valid->password);
            if ($success) {
                $path = config("security.authenticated_route");
                Flash::add("success", "Welcome, " . user()->fullName() . ". You are now signed in");
                header("HX-Redirect: $path");
                exit;
            } else {
                Flash::add("warning", "Invalid email and/or password");
            }
        }
        return $this->index();
    }
}
