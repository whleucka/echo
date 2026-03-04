<?php

declare(strict_types=1);

namespace Tests\Event;

use Echo\Framework\Event\Event;
use Echo\Framework\Event\EventDispatcher;
use Echo\Framework\Event\EventInterface;
use Echo\Framework\Event\ListenerInterface;
use PHPUnit\Framework\TestCase;

class EventDispatcherTest extends TestCase
{
    private EventDispatcher $dispatcher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dispatcher = new EventDispatcher();
    }

    /**
     * Test dispatching fires closure listeners
     */
    public function testDispatchFiresClosureListeners(): void
    {
        $called = false;

        $this->dispatcher->listen(Event::class, function (EventInterface $event) use (&$called) {
            $called = true;
        });

        $this->dispatcher->dispatch(new Event());

        $this->assertTrue($called);
    }

    /**
     * Test dispatch passes the event to listeners
     */
    public function testDispatchPassesEventToListeners(): void
    {
        $receivedEvent = null;

        $this->dispatcher->listen(Event::class, function (EventInterface $event) use (&$receivedEvent) {
            $receivedEvent = $event;
        });

        $original = new Event();
        $this->dispatcher->dispatch($original);

        $this->assertSame($original, $receivedEvent);
    }

    /**
     * Test dispatch returns the event
     */
    public function testDispatchReturnsEvent(): void
    {
        $event = new Event();
        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
    }

    /**
     * Test multiple listeners are called
     */
    public function testMultipleListenersCalled(): void
    {
        $callOrder = [];

        $this->dispatcher->listen(Event::class, function () use (&$callOrder) {
            $callOrder[] = 'first';
        });

        $this->dispatcher->listen(Event::class, function () use (&$callOrder) {
            $callOrder[] = 'second';
        });

        $this->dispatcher->dispatch(new Event());

        $this->assertEquals(['first', 'second'], $callOrder);
    }

    /**
     * Test listeners respect priority (lower = earlier)
     */
    public function testListenerPriority(): void
    {
        $callOrder = [];

        $this->dispatcher->listen(Event::class, function () use (&$callOrder) {
            $callOrder[] = 'low_priority';
        }, 10);

        $this->dispatcher->listen(Event::class, function () use (&$callOrder) {
            $callOrder[] = 'high_priority';
        }, 1);

        $this->dispatcher->listen(Event::class, function () use (&$callOrder) {
            $callOrder[] = 'medium_priority';
        }, 5);

        $this->dispatcher->dispatch(new Event());

        $this->assertEquals(['high_priority', 'medium_priority', 'low_priority'], $callOrder);
    }

    /**
     * Test stoppable event halts propagation
     */
    public function testStoppableEventHaltsPropagation(): void
    {
        $callOrder = [];

        $this->dispatcher->listen(Event::class, function (Event $event) use (&$callOrder) {
            $callOrder[] = 'first';
            $event->stopPropagation();
        });

        $this->dispatcher->listen(Event::class, function () use (&$callOrder) {
            $callOrder[] = 'second';
        });

        $this->dispatcher->dispatch(new Event());

        $this->assertEquals(['first'], $callOrder);
    }

    /**
     * Test hasListeners returns true when listeners exist
     */
    public function testHasListenersTrue(): void
    {
        $this->dispatcher->listen(Event::class, function () {});

        $this->assertTrue($this->dispatcher->hasListeners(Event::class));
    }

    /**
     * Test hasListeners returns false when no listeners
     */
    public function testHasListenersFalse(): void
    {
        $this->assertFalse($this->dispatcher->hasListeners(Event::class));
    }

    /**
     * Test getListeners returns sorted listeners
     */
    public function testGetListenersReturnsSorted(): void
    {
        $listenerA = function () {};
        $listenerB = function () {};

        $this->dispatcher->listen(Event::class, $listenerA, 10);
        $this->dispatcher->listen(Event::class, $listenerB, 1);

        $listeners = $this->dispatcher->getListeners(Event::class);

        $this->assertCount(2, $listeners);
        $this->assertSame($listenerB, $listeners[0]);
        $this->assertSame($listenerA, $listeners[1]);
    }

    /**
     * Test getListeners returns empty for unregistered events
     */
    public function testGetListenersEmpty(): void
    {
        $this->assertEquals([], $this->dispatcher->getListeners('NonExistentEvent'));
    }

    /**
     * Test forget removes all listeners for an event
     */
    public function testForget(): void
    {
        $this->dispatcher->listen(Event::class, function () {});
        $this->dispatcher->listen(Event::class, function () {});

        $this->assertTrue($this->dispatcher->hasListeners(Event::class));

        $this->dispatcher->forget(Event::class);

        $this->assertFalse($this->dispatcher->hasListeners(Event::class));
        $this->assertEquals([], $this->dispatcher->getListeners(Event::class));
    }

    /**
     * Test dispatch with no listeners does nothing
     */
    public function testDispatchNoListeners(): void
    {
        $event = new Event();
        $result = $this->dispatcher->dispatch($event);

        $this->assertSame($event, $result);
        $this->assertFalse($event->isPropagationStopped());
    }

    /**
     * Test typed events only fire matching listeners
     */
    public function testTypedEventsFireMatchingListeners(): void
    {
        $eventACalled = false;
        $eventBCalled = false;

        $eventA = new class extends Event {
            public function getName(): string { return 'EventA'; }
        };

        $eventB = new class extends Event {
            public function getName(): string { return 'EventB'; }
        };

        $this->dispatcher->listen('EventA', function () use (&$eventACalled) {
            $eventACalled = true;
        });

        $this->dispatcher->listen('EventB', function () use (&$eventBCalled) {
            $eventBCalled = true;
        });

        $this->dispatcher->dispatch($eventA);

        $this->assertTrue($eventACalled);
        $this->assertFalse($eventBCalled);
    }
}
