<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Http\Controller;
use Echo\Framework\Http\Response;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(pathPrefix: "/admin", middleware: ["auth"])]
class AdminController extends Controller
{
    #[Get("/", "admin.redirect")]
    public function home(): Response
    {
        $path = uri("dashboard.admin.index");
        return redirect($path);
    }
}
