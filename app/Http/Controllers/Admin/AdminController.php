<?php

namespace App\Http\Controllers\Admin;

use Echo\Framework\Http\AdminController as BaseAdminController;
use Echo\Framework\Routing\Route\Get;

class AdminController extends BaseAdminController
{
    #[Get("/", "home")]
    public function home(): void
    {
        header('Location: /admin/dashboard');
        exit;
    }

    protected function renderTable(): string
    {
        return '';
    }
}
