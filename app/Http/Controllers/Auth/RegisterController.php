<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\RegisterService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Http\Response;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\{Get, Post};
use Echo\Framework\Session\Flash;

#[Group(pathPrefix: '/admin')]
class RegisterController extends Controller
{
    public function __construct(private RegisterService $service)
    {
        if (!config("security.register_enabled")) {
            $this->permissionDenied();
        }
    }

    #[Get("/register", "auth.register.index")]
    public function index(): string
    {
        return $this->render("auth/register/index.html.twig");
    }

    #[Post("/register", "auth.register.post", ["max_requests" => 10, "decay_seconds" => 60])]
    public function post(): string|Response
    {
        $this->setValidationMessage("password.min_length", "Must be at least 10 characters");
        $this->setValidationMessage("password.regex", "Must contain 1 upper case, 1 digit, 1 symbol");
        $this->setValidationMessage("password_match.match", "Password does not match");
        $valid = $this->validate([
            "first_name" => ["required"],
            "surname" => ["required"],
            "email" => ["required", "email", "unique:users"],
            "password" => ["required", "min_length:10", "regex:^(?=.*[A-Z])(?=.*\W)(?=.*\d).+$"],
            "password_match" => ["required", "match:password"],
        ]);
        if ($valid) {
            $success = $this->service->register($valid->first_name, $valid->surname, $valid->email, $valid->password);
            if ($success) {
                $path = config("security.authenticated_route");
                return redirect($path)->withFlash("success", "Welcome, " . user()->fullName() . "! You are now signed in");
            } else {
                Flash::add("warning", "Failed to register new account");
            }
        }
        return $this->index();
    }
}
