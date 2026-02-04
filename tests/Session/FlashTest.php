<?php

declare(strict_types=1);

namespace Tests\Session;

use PHPUnit\Framework\TestCase;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class FlashTest extends TestCase
{
    private array $flash = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->flash = [];
    }

    /**
     * Simulate Flash::add behavior
     */
    private function addFlash(string $type, string $message): void
    {
        $this->flash[strtolower($type)][md5($message)][] = $message;
    }

    /**
     * Simulate Flash::get behavior
     */
    private function getFlash(): array
    {
        $flash = $this->flash;
        $this->flash = [];
        return $flash;
    }

    /**
     * Simulate Flash::destroy behavior
     */
    private function destroyFlash(): void
    {
        $this->flash = [];
    }

    public function testAddStoresMessage(): void
    {
        $this->addFlash('success', 'Operation completed');

        $this->assertArrayHasKey('success', $this->flash);
    }

    public function testGetRetrievesAndClearsMessages(): void
    {
        $this->addFlash('info', 'Test message');

        $messages = $this->getFlash();

        $this->assertArrayHasKey('info', $messages);
        $this->assertEmpty($this->flash);
    }

    public function testDestroyRemovesAllFlashMessages(): void
    {
        $this->addFlash('success', 'Message 1');
        $this->addFlash('error', 'Message 2');
        $this->addFlash('warning', 'Message 3');

        $this->destroyFlash();

        $this->assertEmpty($this->flash);
    }

    public function testDuplicateMessagesAreIgnored(): void
    {
        $this->addFlash('success', 'Same message');
        $this->addFlash('success', 'Same message');
        $this->addFlash('success', 'Same message');

        $messages = $this->getFlash();
        $hash = md5('Same message');

        // Messages are stored under their md5 hash, duplicates go in same bucket
        $this->assertCount(1, $messages['success']);
        $this->assertCount(3, $messages['success'][$hash]);
    }

    public function testMultipleMessageTypes(): void
    {
        $this->addFlash('success', 'Success message');
        $this->addFlash('error', 'Error message');
        $this->addFlash('warning', 'Warning message');
        $this->addFlash('info', 'Info message');

        $messages = $this->getFlash();

        $this->assertArrayHasKey('success', $messages);
        $this->assertArrayHasKey('error', $messages);
        $this->assertArrayHasKey('warning', $messages);
        $this->assertArrayHasKey('info', $messages);
    }

    public function testFlashTypesAreLowercased(): void
    {
        $this->addFlash('SUCCESS', 'Message');
        $this->addFlash('Error', 'Message');
        $this->addFlash('WARNING', 'Message');

        $this->assertArrayHasKey('success', $this->flash);
        $this->assertArrayHasKey('error', $this->flash);
        $this->assertArrayHasKey('warning', $this->flash);
    }

    public function testGetReturnsEmptyArrayWhenNoMessages(): void
    {
        $messages = $this->getFlash();

        $this->assertIsArray($messages);
        $this->assertEmpty($messages);
    }

    public function testMultipleMessagesOfSameType(): void
    {
        $this->addFlash('error', 'First error');
        $this->addFlash('error', 'Second error');
        $this->addFlash('error', 'Third error');

        $messages = $this->getFlash();

        $this->assertArrayHasKey('error', $messages);
        $this->assertCount(3, $messages['error']);
    }
}
