<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use olvlvl\EventDispatcher\ListenerProviderFilter;
use olvlvl\EventDispatcher\ListenerProviderWithMap;
use PHPUnit\Framework\TestCase;

final class ListenerProviderFilterTest extends TestCase
{
    public function testGetListenersForEvent(): void
    {
        $listener_1 = function () {
        };
        $listener_2 = function () {
        };

        $provider = new ListenerProviderFilter(
            new ListenerProviderWithMap([
                SampleEventA::class => [ $listener_1, $listener_2 ],
                SampleEventC::class => [ $listener_1, $listener_2 ],
            ]),
            function (object $event, callable $listener) use ($listener_1, $listener_2): bool {
                if ($event instanceof SampleEventA && $listener === $listener_1) {
                    return false;
                }

                if ($event instanceof SampleEventC && $listener === $listener_2) {
                    return false;
                }

                return true;
            }
        );

        $this->assertSame(
            [ $listener_2 ],
            iterable_to_array($provider->getListenersForEvent(new SampleEventA()))
        );

        $this->assertSame(
            [ $listener_1 ],
            iterable_to_array($provider->getListenersForEvent(new SampleEventC()))
        );
    }
}
