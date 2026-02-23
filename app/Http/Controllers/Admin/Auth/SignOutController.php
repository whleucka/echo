<?php

namespace App\Http\Controllers\Admin\Auth;

use App\Services\Auth\SignInService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Http\Response;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Post;

#[Group(subdomain: 'admin', namePrefix: 'auth')]
class SignOutController extends Controller
{
    public function __construct(private SignInService $service)
    {
    }

    #[Post("/sign-out", "sign-out.post", ["auth"])]
    public function post(): Response
    {
        $this->service->signOut();
        $path = uri("auth.sign-in.index");
        return redirect($path)->withFlash("success", "You are now signed out");
    }
}
