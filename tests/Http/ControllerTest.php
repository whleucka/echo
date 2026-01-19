<?php declare(strict_types=1);

namespace Tests\Http;

use Echo\Framework\Http\Controller;
use Echo\Framework\Http\Request;
use PHPUnit\Framework\TestCase;

class ControllerTest extends TestCase
{
    public function testValidate() 
    {
        $c = new BasicController();
        $request = new Request(request: [
            "email" => "test@test.com",
            "password" => "password1234",
            "foo" => "bar",
            "bar" => "baz"
        ]);
        $c->setRequest($request);
        $valid = $c->validate([
            "email" => ["required", "email"],
            "password" => ["required"],
            "foo" => []
        ]);
        $this->assertNotNull($valid);
        $this->assertEquals((object)[
            "email" => "test@test.com",
            "password" => "password1234",
            "foo" => "bar"
        ], $valid);
    }
}

class BasicController extends Controller
{
}
