<?php

namespace App\Http\Controllers\Admin;

use App\Services\Admin\ThemeService;
use Echo\Framework\Routing\Route\Get;

class ThemeController extends AdminController
{
    public function __construct(private ThemeService $service)
    {
    }

    #[Get("/theme/toggle", "admin.theme.toggle")]
    public function toggle(): string
    {
        $this->service->toggle();
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Pragma: no-cache");
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
