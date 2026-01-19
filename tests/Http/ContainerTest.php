<?php declare(strict_types=1);

namespace Tests\Http;

use Echo\Framework\Http\Controller;
use PHPUnit\Framework\TestCase;

class ContainerTest extends TestCase
{
    public function testConstructorDependencyInjection()
    {
        $tc = container()->get(TestController::class);
        $this->assertTrue($tc->td->test());
    }
}

class TestDependency
{
    public function test()
    {
        return true;
    }
}

class TestController extends Controller
{
    public function __construct(public TestDependency $td)
    {
    }
}
