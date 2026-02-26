<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\ThemeService;
use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Group;
use Echo\Framework\Routing\Route\Get;

#[Group(subdomain: 'admin', middleware: ["auth"])]
class ThemeController extends Controller
{
    public function __construct(private ThemeService $service)
    {
    }

    #[Get("/theme/toggle", "admin.theme.toggle")]
    public function toggle(): string
    {
        $this->service->toggle();
        return $this->render("admin/nav-top.html.twig", $this->getNavTopData());
    }

    private function getNavTopData(): array
    {
        $sidebarService = new \App\Services\Admin\SidebarService();
        return [
            "dark_mode" => $this->service->isDarkMode(),
            "sidebar" => [
                "links" => $sidebarService->getLinks(null, user()),
            ],
            "user" => [
                "avatar" => user()->avatar
                    ? user()->avatar()->path
                    : user()->gravatar(38),
            ],
        ];
    }
}
