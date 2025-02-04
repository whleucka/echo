<?php

namespace App\Http\Controllers\Auth;

use App\Providers\Auth\SignInService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Route\{Get, Post};

class SignInController extends Controller
{
    public function __construct(private SignInService $provider)
    {
    }

    #[Get("/sign-in", "auth.sign-in.index")]
    public function index(): string
    {
        return $this->render("auth/sign-in.html.twig");
    }

    #[Post("/sign-in", "auth.sign-in.post")]
    public function post()
    {
        $valid = $this->validate([
            "email" => ["required", "email"],
            "password" => ["required"],
        ]);
        if ($valid) {
            dd($valid);
            $success = $this->provider->signIn($valid->email, $valid->password);
            if ($success) {
                die("wip: sign in success");
            }
        }
        return $this->index();
    }
}
