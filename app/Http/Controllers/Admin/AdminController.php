<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(path_prefix: "/admin", middleware: ["auth"])]
class AdminController extends Controller
{
    #[Get("/", "home")]
    public function home(): void
    {
        header('Location: /admin/dashboard');
        exit;
    }
}
