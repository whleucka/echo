<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\SidebarService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(pathPrefix: "/admin", middleware: ["auth"])]
class SidebarController extends Controller
{
    public function __construct(private SidebarService $service)
    {
    }

    #[Get("/sidebar", "admin.sidebar.load")]
    public function load(): string
    {
        $links = $this->service->getLinks([], [], user());
        // Non-admin users must be granted permission
        return $this->render("admin/sidebar.html.twig", [
            "hide" => $this->service->getState(),
            "links" => $links
        ]);
    }

    #[Get("/sidebar/toggle", "admin.sidebar.toggle")]
    public function toggle(): string
    {
        $this->service->toggleState();
        return $this->load();
    }
}
