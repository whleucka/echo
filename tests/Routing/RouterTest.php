<?php

declare(strict_types=1);

namespace Tests\Routing;

use Echo\Framework\Http\Controller;
use Echo\Framework\Routing\Collector;
use Echo\Framework\Routing\Route\Get;
use Echo\Framework\Routing\Router;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
{
    private function router(array $classes = [Routes::class])
    {
        $collector = new Collector();
        foreach ($classes as $class) {
            $collector->register($class);
        }
        return new Router($collector);
    }

    private function dispatchRoute(string $uri, string $method, array $classes = [Routes::class])
    {
        return $this->router($classes)->dispatch($uri, $method);
    }

    public function testDispatchRoute()
    {
        $route = $this->dispatchRoute("/", "GET");

        // Test controller
        $this->assertSame("Tests\Routing\Routes", $route["controller"]);
        // Test method
        $this->assertSame("index", $route["method"]);
        // Test middleware
        $this->assertSame(["auth"], $route["middleware"]);
        // Test name
        $this->assertSame("routes.index", $route["name"]);
    }

    public function testRouteMethod()
    {
        $route = $this->dispatchRoute("/numbers/1", "GET");
        $this->assertSame("numbers", $route["method"]);

        $route = $this->dispatchRoute("/numbers/9", "GET");
        $this->assertSame("numbers", $route["method"]);

        $route = $this->dispatchRoute("/numbers/10", "GET");
        $this->assertSame(null, $route);

        $route = $this->dispatchRoute("/slug/this-is-a-slug", "GET");
        $this->assertSame("slug", $route["method"]);
    }

    public function testRouteParam()
    {
        $route = $this->dispatchRoute("/id/420", "GET");
        $this->assertSame("id", $route["method"]);
        $this->assertSame(['420'], $route["params"]);


        $route = $this->dispatchRoute("/id/69", "GET");
        $this->assertSame("id", $route["method"]);
        $this->assertSame(['69'], $route["params"]);
    }

    public function testRouteMultipleParams()
    {
        $route = $this->dispatchRoute("/user/67def236-954e-4d78-8af0-d9cca0bee9a0/9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08", "GET");
        $this->assertSame("user", $route["method"]);
        $this->assertSame(["67def236-954e-4d78-8af0-d9cca0bee9a0", "9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08"], $route["params"]);
    }

    public function testRouteRegexParam()
    {
        $route = $this->dispatchRoute("/colour/blue", "GET");
        $this->assertSame("blue_red", $route["method"]);

        $route = $this->dispatchRoute("/colour/red", "GET");
        $this->assertSame("blue_red", $route["method"]);

        $route = $this->dispatchRoute("/colour/purple", "GET");
        $this->assertSame(null, $route);
    }

    public function testRouteCaseSensitivity()
    {
        $route = $this->dispatchRoute("/Colour/blue", "GET");
        $this->assertSame(null, $route);
    }

    public function testRouteTrailingSlash()
    {
        $route = $this->dispatchRoute("/slug/this-is-a-slug/", "GET");
        $this->assertSame(null, $route);
    }

    public function testRouteEmptyUri()
    {
        $route = $this->dispatchRoute("", "GET");
        $this->assertSame(null, $route);
    }

    public function testInvalidHttpMethod()
    {
        $route = $this->dispatchRoute("/numbers/1", "POST");
        $this->assertSame(null, $route);
    }

    public function testRouteNumeric()
    {
        $route = $this->dispatchRoute("/numbers/0", "GET");
        $this->assertSame("numbers", $route["method"]);

        $route = $this->dispatchRoute("/numbers/9", "GET");
        $this->assertSame("numbers", $route["method"]);

        $route = $this->dispatchRoute("/numbers/10", "GET");
        $this->assertSame(null, $route);
    }

    public function testDuplicateRouteName()
    {
        $this->expectException(\Exception::class);
        $this->dispatchRoute("/", "GET", [Routes::class, DuplicateName::class]);
    }

    public function testRouteSpecialCharacters()
    {
        $route = $this->dispatchRoute("/id/@#$%", "GET");
        $this->assertSame(null, $route); // Should not match `{id}`

        $route = $this->dispatchRoute("/user/abc*/def!", "GET");
        $this->assertSame(null, $route); // Invalid UUID and token
    }

    public function testRouteWithDotInParam()
    {
        $route = $this->dispatchRoute("/repo/mantis.nvim", "GET");
        $this->assertSame("repo", $route["method"]);
        $this->assertSame(['mantis.nvim'], $route["params"]);
    }

    public function testSearchUri()
    {
        $uri = $this->router()->searchUri('routes.id', 420);
        $this->assertSame('/id/420', $uri);

        $uri = $this->router()->searchUri('routes.user', '29aa8ed5-bf51-49b2-88db-a5664e3b437c', 'a97ba656-b4d0-4976-b31e-413ac4ffe61b');
        $this->assertSame('/user/29aa8ed5-bf51-49b2-88db-a5664e3b437c/a97ba656-b4d0-4976-b31e-413ac4ffe61b', $uri);
    }
}

class Routes extends Controller
{
    #[Get("/", "routes.index", ["auth"])]
    public function index()
    {
        return "index";
    }

    #[Get("/numbers/[0-9]", "routes.numbers")]
    public function numbers()
    {
        return "numbers";
    }

    #[Get("/colour/(blue|red)", "routes.blue_red")]
    public function blue_red()
    {
        return "blue_red";
    }

    #[Get("/slug/this-is-a-slug", "routes.slug")]
    public function slug()
    {
        return 'slug';
    }

    #[Get("/id/{id}", "routes.id")]
    public function id(int $id)
    {
        return $id;
    }

    #[Get("/user/{uuid}/{token}", "routes.user")]
    public function user(string $uuid, string $token)
    {
        return $uuid.$token;
    }

    #[Get("/repo/{repo}", "routes.repo")]
    public function repo(string $repo)
    {
        return $repo;
    }

    #[Get("/id/testing", "routes.testing")]
    public function testing()
    {
        return 'testing';
    }

}

class DuplicateName extends Controller
{
    #[Get("/testing-duplicate-name", "routes.index")]
    public function duplicat_name()
    {
        return 'index';
    }
}
