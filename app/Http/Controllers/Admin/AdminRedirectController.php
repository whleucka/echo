<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Http\Controller;
use Echo\Framework\Http\RedirectResponse;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(subdomain: 'admin', middleware: ["auth"])]
class AdminRedirectController extends Controller
{
    #[Get("/", "admin.root")]
    public function index(): RedirectResponse
    {
        return redirect('/dashboard');
    }
}
