<?php

declare(strict_types=1);

namespace Tests\Session;

use Echo\Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Session class
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionIntegrationTest extends TestCase
{
    private Session $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->session = Session::getInstance();
    }

    protected function tearDown(): void
    {
        $this->session->destroy();
        parent::tearDown();
    }

    public function testSetAndGetValue(): void
    {
        $this->session->set('test_key', 'test_value');

        $this->assertSame('test_value', $this->session->get('test_key'));
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $this->session->set('existing', 'value');

        $this->assertTrue($this->session->has('existing'));
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertFalse($this->session->has('nonexistent'));
    }

    public function testDeleteRemovesKey(): void
    {
        $this->session->set('to_delete', 'value');
        $this->session->delete('to_delete');

        $this->assertFalse($this->session->has('to_delete'));
        $this->assertNull($this->session->get('to_delete'));
    }

    public function testDestroyRemovesAllData(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');
        $this->session->destroy();

        $this->assertEmpty($this->session->all());
    }

    public function testAllReturnsAllSessionData(): void
    {
        $this->session->set('key1', 'value1');
        $this->session->set('key2', 'value2');
        $this->session->set('key3', 'value3');

        $all = $this->session->all();

        $this->assertArrayHasKey('key1', $all);
        $this->assertArrayHasKey('key2', $all);
        $this->assertArrayHasKey('key3', $all);
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($this->session->get('missing'));
    }

    public function testSetOverwritesExistingValue(): void
    {
        $this->session->set('key', 'original');
        $this->session->set('key', 'updated');

        $this->assertSame('updated', $this->session->get('key'));
    }

    public function testSetAcceptsArrayValues(): void
    {
        $this->session->set('array_key', ['a', 'b', 'c']);

        $result = $this->session->get('array_key');
        $this->assertIsArray($result);
        $this->assertCount(3, $result);
    }

    public function testSetAcceptsNestedArrayValues(): void
    {
        $this->session->set('nested', [
            'level1' => [
                'level2' => 'deep_value'
            ]
        ]);

        $result = $this->session->get('nested');
        $this->assertSame('deep_value', $result['level1']['level2']);
    }

    public function testRegenerateChangesSessionId(): void
    {
        $this->session->set('persist', 'value');

        // Get original session ID (session is already started by set())
        $originalId = session_id();

        // Regenerate
        $this->session->regenerate(true);

        // Get new session ID
        $newId = session_id();

        $this->assertNotEquals($originalId, $newId);
        // Data should persist
        $this->assertSame('value', $this->session->get('persist'));
    }

    public function testSingletonReturnsSameInstance(): void
    {
        $instance1 = Session::getInstance();
        $instance2 = Session::getInstance();

        $this->assertSame($instance1, $instance2);
    }
}
