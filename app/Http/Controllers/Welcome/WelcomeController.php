<?php

namespace App\Http\Controllers\Welcome;

use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Route\Get;

class WelcomeController extends Controller
{
    #[Get("/", "welcome.index")] 
    public function index(): string
    {
        return $this->render("welcome/index.html.twig");
    }
}
