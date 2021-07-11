<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use olvlvl\EventDispatcher\BasicEventDispatcher;
use olvlvl\EventDispatcher\ListenerProviderWithMap;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;

final class BasicEventDispatcherTest extends TestCase
{
    public function testDispatch(): void
    {
        $called = 0;

        $listenerProvider = new ListenerProviderWithMap([
            SampleEventA::class => [
                function () use (&$called): void {
                    $called++;
                },
                function () use (&$called): void {
                    $called++;
                },
            ]
        ]);

        $event = new SampleEventA();
        $dispatcher = new BasicEventDispatcher($listenerProvider);
        $this->assertSame($event, $dispatcher->dispatch($event));
        $this->assertEquals(2, $called);
    }

    public function testDispatchStoppableEvent(): void
    {
        $called = false;

        $listenerProvider = new ListenerProviderWithMap([
            SampleStoppableEvent::class => [
                function () use (&$called): void {
                    $called = true;
                },
                function (SampleStoppableEvent $event): void {
                    $event->stopped = true;
                },
                function (): void {
                    $this->fail("should not be called");
                },
            ]
        ]);

        $event = new SampleStoppableEvent();
        $dispatcher = new BasicEventDispatcher($listenerProvider);
        $this->assertSame($event, $dispatcher->dispatch($event));
        $this->assertTrue($called);
        $this->assertTrue($event->stopped);
    }

    public function testDispatchStoppableEventAlreadyStopped(): void
    {
        $event = new SampleStoppableEvent();
        $event->stopped = true;

        $listenerProvider = $this->createMock(ListenerProviderInterface::class);
        $listenerProvider->expects($this->never())->method('getListenersForEvent');

        $dispatcher = new BasicEventDispatcher($listenerProvider);
        $this->assertSame($event, $dispatcher->dispatch($event));
    }
}
