<?php

declare(strict_types=1);

namespace Tests\Event;

use Echo\Framework\Event\Event;
use PHPUnit\Framework\TestCase;

class EventTest extends TestCase
{
    /**
     * Test event getName returns FQCN by default
     */
    public function testGetNameReturnsFqcn(): void
    {
        $event = new Event();

        $this->assertEquals(Event::class, $event->getName());
    }

    /**
     * Test event getName returns child class FQCN
     */
    public function testGetNameReturnsChildFqcn(): void
    {
        $event = new class extends Event {};

        $this->assertNotEquals(Event::class, $event->getName());
        $this->assertStringContainsString('@anonymous', $event->getName());
    }

    /**
     * Test propagation is not stopped by default
     */
    public function testPropagationNotStoppedByDefault(): void
    {
        $event = new Event();

        $this->assertFalse($event->isPropagationStopped());
    }

    /**
     * Test stopPropagation marks event as stopped
     */
    public function testStopPropagation(): void
    {
        $event = new Event();
        $event->stopPropagation();

        $this->assertTrue($event->isPropagationStopped());
    }
}
