<?php

namespace App\Http\Controllers\Auth;

use App\Services\Auth\SignInService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Post;
use Echo\Framework\Session\Flash;

#[Group(path_prefix: "/admin")]
class SignOutController extends Controller
{
    public function __construct(private SignInService $service)
    {
    }

    #[Post("/sign-out", "auth.sign-out.post", ["auth", "csrf"])]
    public function post(): void
    {
        $this->service->signOut();
        Flash::add("success", "You are now signed out");
        $path = uri("auth.sign-in.index");
        header("Location: $path");
    }
}
