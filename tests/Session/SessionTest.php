<?php

declare(strict_types=1);

namespace Tests\Session;

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class SessionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testSetAndGetValue(): void
    {
        $_SESSION['test_key'] = 'test_value';

        $this->assertSame('test_value', $_SESSION['test_key']);
    }

    public function testHasReturnsTrueForExistingKey(): void
    {
        $_SESSION['existing'] = 'value';

        $this->assertArrayHasKey('existing', $_SESSION);
    }

    public function testHasReturnsFalseForMissingKey(): void
    {
        $this->assertArrayNotHasKey('nonexistent', $_SESSION);
    }

    public function testDeleteRemovesKey(): void
    {
        $_SESSION['to_delete'] = 'value';
        unset($_SESSION['to_delete']);

        $this->assertArrayNotHasKey('to_delete', $_SESSION);
    }

    public function testDestroyRemovesAllData(): void
    {
        $_SESSION['key1'] = 'value1';
        $_SESSION['key2'] = 'value2';
        $_SESSION = [];

        $this->assertEmpty($_SESSION);
    }

    public function testAllReturnsAllSessionData(): void
    {
        $_SESSION['key1'] = 'value1';
        $_SESSION['key2'] = 'value2';
        $_SESSION['key3'] = 'value3';

        $this->assertCount(3, $_SESSION);
        $this->assertSame('value1', $_SESSION['key1']);
        $this->assertSame('value2', $_SESSION['key2']);
        $this->assertSame('value3', $_SESSION['key3']);
    }

    public function testGetReturnsNullForMissingKey(): void
    {
        $this->assertNull($_SESSION['missing'] ?? null);
    }

    public function testSetOverwritesExistingValue(): void
    {
        $_SESSION['key'] = 'original';
        $_SESSION['key'] = 'updated';

        $this->assertSame('updated', $_SESSION['key']);
    }

    public function testSetAcceptsArrayValues(): void
    {
        $_SESSION['array_key'] = ['a', 'b', 'c'];

        $this->assertIsArray($_SESSION['array_key']);
        $this->assertCount(3, $_SESSION['array_key']);
    }

    public function testSetAcceptsNestedArrayValues(): void
    {
        $_SESSION['nested'] = [
            'level1' => [
                'level2' => 'deep_value'
            ]
        ];

        $this->assertSame('deep_value', $_SESSION['nested']['level1']['level2']);
    }
}
