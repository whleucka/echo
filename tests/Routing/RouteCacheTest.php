<?php declare(strict_types=1);

namespace Tests\Routing;

use Echo\Framework\Routing\RouteCache;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class RouteCacheTest extends TestCase
{
    private string $tempDir;
    private string $tempCachePath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/echo_test_cache_' . uniqid();
        mkdir($this->tempDir, 0755, true);
        $this->tempCachePath = $this->tempDir . '/routes.php';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempCachePath)) {
            unlink($this->tempCachePath);
        }
        if (is_dir($this->tempDir)) {
            rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    private function createCache(): RouteCache
    {
        $cache = (new ReflectionClass(RouteCache::class))->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(RouteCache::class, 'cachePath');
        $prop->setValue($cache, $this->tempCachePath);
        return $cache;
    }

    // ─── compileRoutes ──────────────────────────────────────────

    public function testCompileRoutesWithParameterizedRoutes()
    {
        $cache = $this->createCache();
        $routes = [
            '/users/{id}' => ['GET' => ['controller' => 'UserController', 'method' => 'show']],
            '/posts' => ['GET' => ['controller' => 'PostController', 'method' => 'index']],
            '/posts/{id}/comments/{commentId}' => ['GET' => ['controller' => 'CommentController', 'method' => 'show']],
        ];

        $compiled = $cache->compileRoutes($routes);

        $this->assertArrayHasKey('routes', $compiled);
        $this->assertArrayHasKey('patterns', $compiled);
        $this->assertSame($routes, $compiled['routes']);

        // Only parameterized routes should have compiled patterns
        $this->assertArrayHasKey('/users/{id}', $compiled['patterns']);
        $this->assertArrayHasKey('/posts/{id}/comments/{commentId}', $compiled['patterns']);
        $this->assertArrayNotHasKey('/posts', $compiled['patterns']);
    }

    public function testCompileRoutesPatternFormat()
    {
        $cache = $this->createCache();
        $routes = [
            '/users/{id}' => ['GET' => []],
        ];

        $compiled = $cache->compileRoutes($routes);
        $pattern = $compiled['patterns']['/users/{id}'];

        // Pattern should match valid params
        $this->assertMatchesRegularExpression($pattern, '/users/123');
        $this->assertMatchesRegularExpression($pattern, '/users/abc');
        $this->assertMatchesRegularExpression($pattern, '/users/test-name');
        $this->assertMatchesRegularExpression($pattern, '/users/file.txt');

        // Pattern should not match invalid
        $this->assertDoesNotMatchRegularExpression($pattern, '/users/');
        $this->assertDoesNotMatchRegularExpression($pattern, '/users/123/extra');
    }

    public function testCompileRoutesWithNoParams()
    {
        $cache = $this->createCache();
        $routes = [
            '/users' => ['GET' => []],
            '/posts' => ['GET' => []],
        ];

        $compiled = $cache->compileRoutes($routes);
        $this->assertEmpty($compiled['patterns']);
        $this->assertSame($routes, $compiled['routes']);
    }

    public function testCompileRoutesEmptyInput()
    {
        $cache = $this->createCache();
        $compiled = $cache->compileRoutes([]);
        $this->assertSame(['routes' => [], 'patterns' => []], $compiled);
    }

    // ─── isCached / cache / get / clear ─────────────────────────

    public function testIsCachedReturnsFalseWhenNoCache()
    {
        $cache = $this->createCache();
        $this->assertFalse($cache->isCached());
    }

    public function testCacheAndRetrieve()
    {
        $cache = $this->createCache();
        $routes = [
            '/users/{id}' => ['GET' => ['controller' => 'UserController']],
            '/posts' => ['GET' => ['controller' => 'PostController']],
        ];

        $result = $cache->cache($routes);
        $this->assertTrue($result);
        $this->assertTrue($cache->isCached());

        $data = $cache->get();
        $this->assertArrayHasKey('routes', $data);
        $this->assertArrayHasKey('patterns', $data);
        $this->assertSame($routes, $data['routes']);
        $this->assertArrayHasKey('/users/{id}', $data['patterns']);
    }

    public function testGetRoutesReturnsOnlyRoutes()
    {
        $cache = $this->createCache();
        $routes = ['/users' => ['GET' => []]];

        $cache->cache($routes);
        $this->assertSame($routes, $cache->getRoutes());
    }

    public function testGetPatternsReturnsOnlyPatterns()
    {
        $cache = $this->createCache();
        $routes = ['/users/{id}' => ['GET' => []]];

        $cache->cache($routes);
        $patterns = $cache->getPatterns();
        $this->assertArrayHasKey('/users/{id}', $patterns);
    }

    public function testGetReturnsEmptyWhenNotCached()
    {
        $cache = $this->createCache();
        $data = $cache->get();
        $this->assertSame(['routes' => [], 'patterns' => []], $data);
    }

    public function testClearRemovesCacheFile()
    {
        $cache = $this->createCache();
        $cache->cache(['/test' => ['GET' => []]]);
        $this->assertTrue($cache->isCached());

        $result = $cache->clear();
        $this->assertTrue($result);
        $this->assertFalse($cache->isCached());
    }

    public function testClearWhenNoCacheReturnsTrue()
    {
        $cache = $this->createCache();
        $this->assertTrue($cache->clear());
    }

    public function testGetCachePath()
    {
        $cache = $this->createCache();
        $this->assertSame($this->tempCachePath, $cache->getCachePath());
    }

    // ─── cache creates directory if missing ─────────────────────

    public function testCacheCreatesDirectoryIfNeeded()
    {
        $nestedPath = $this->tempDir . '/nested/deep/routes.php';
        $cache = (new ReflectionClass(RouteCache::class))->newInstanceWithoutConstructor();
        $prop = new \ReflectionProperty(RouteCache::class, 'cachePath');
        $prop->setValue($cache, $nestedPath);

        $cache->cache(['/test' => ['GET' => []]]);
        $this->assertFileExists($nestedPath);

        // Cleanup
        unlink($nestedPath);
        rmdir($this->tempDir . '/nested/deep');
        rmdir($this->tempDir . '/nested');
    }
}
