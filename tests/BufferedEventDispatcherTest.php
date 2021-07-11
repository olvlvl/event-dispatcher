<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use olvlvl\EventDispatcher\BufferedEventDispatcher;
use PHPUnit\Framework\TestCase;

final class BufferedEventDispatcherTest extends TestCase
{
    public function testDispatch(): void
    {
        $recorder = new RecorderEventDispatcher();
        $dispatcher = new BufferedEventDispatcher(
            $recorder,
            function (object $event): bool {
                return !$event instanceof SampleEventB;
            }
        );

        $event1 = new SampleEventA();
        $event2 = new SampleEventB(); // should be dispatched immediately
        $event3 = new SampleEventC();
        $stoppedEvent = new SampleStoppableEvent(true); // should be discarded

        $this->assertSame($event1, $dispatcher->dispatch($event1));
        $this->assertSame($event2, $dispatcher->dispatch($event2));
        $this->assertSame($event3, $dispatcher->dispatch($event3));
        $this->assertSame($stoppedEvent, $dispatcher->dispatch($stoppedEvent));
        $this->assertSame([ $event2 ], $recorder->events);

        $dispatched = $dispatcher->dispatchBufferedEvents();
        $this->assertSame([ $event1, $event3 ], $dispatched);
        $this->assertSame([ $event2, $event1, $event3 ], $recorder->events);
        $this->assertCount(0, $dispatcher->dispatchBufferedEvents());
    }
}
