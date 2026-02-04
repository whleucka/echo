<?php

declare(strict_types=1);

namespace Tests\Session;

use Echo\Framework\Session\Flash;
use Echo\Framework\Session\Session;
use PHPUnit\Framework\TestCase;

/**
 * Integration tests for the Flash class
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FlashIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Flash::destroy();
    }

    protected function tearDown(): void
    {
        Flash::destroy();
        Session::getInstance()->destroy();
        parent::tearDown();
    }

    public function testAddStoresMessage(): void
    {
        Flash::add('success', 'Operation completed');

        $messages = Flash::get();

        $this->assertArrayHasKey('success', $messages);
    }

    public function testGetRetrievesAndClearsMessages(): void
    {
        Flash::add('info', 'Test message');

        $messages = Flash::get();
        $this->assertArrayHasKey('info', $messages);

        // Second get should be empty
        $messages = Flash::get();
        $this->assertEmpty($messages);
    }

    public function testDestroyRemovesAllFlashMessages(): void
    {
        Flash::add('success', 'Message 1');
        Flash::add('error', 'Message 2');
        Flash::add('warning', 'Message 3');

        Flash::destroy();

        $messages = Flash::get();
        $this->assertEmpty($messages);
    }

    public function testMultipleMessageTypes(): void
    {
        Flash::add('success', 'Success message');
        Flash::add('error', 'Error message');
        Flash::add('warning', 'Warning message');
        Flash::add('info', 'Info message');

        $messages = Flash::get();

        $this->assertArrayHasKey('success', $messages);
        $this->assertArrayHasKey('error', $messages);
        $this->assertArrayHasKey('warning', $messages);
        $this->assertArrayHasKey('info', $messages);
    }

    public function testFlashTypesAreLowercased(): void
    {
        Flash::add('SUCCESS', 'Message 1');
        Flash::add('Error', 'Message 2');
        Flash::add('WARNING', 'Message 3');

        $messages = Flash::get();

        $this->assertArrayHasKey('success', $messages);
        $this->assertArrayHasKey('error', $messages);
        $this->assertArrayHasKey('warning', $messages);
    }

    public function testGetReturnsEmptyArrayWhenNoMessages(): void
    {
        $messages = Flash::get();

        $this->assertIsArray($messages);
        $this->assertEmpty($messages);
    }

    public function testMultipleMessagesOfSameType(): void
    {
        Flash::add('error', 'First error');
        Flash::add('error', 'Second error');
        Flash::add('error', 'Third error');

        $messages = Flash::get();

        $this->assertArrayHasKey('error', $messages);
        // Each unique message gets its own hash key
        $this->assertCount(3, $messages['error']);
    }

    public function testDuplicateMessagesGroupedByHash(): void
    {
        Flash::add('success', 'Same message');
        Flash::add('success', 'Same message');
        Flash::add('success', 'Same message');

        $messages = Flash::get();

        // Duplicates are stored under the same md5 hash
        $this->assertCount(1, $messages['success']);
        $hash = md5('Same message');
        $this->assertCount(3, $messages['success'][$hash]);
    }

    public function testFlashPersistsAcrossSessionAccess(): void
    {
        Flash::add('info', 'Persistent message');

        // Simulate reading session data (like a page load)
        $session = Session::getInstance();
        $flashData = $session->get('flash');

        $this->assertNotEmpty($flashData);
        $this->assertArrayHasKey('info', $flashData);
    }
}
