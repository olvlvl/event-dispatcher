<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use olvlvl\EventDispatcher\ListenerProviderWithContainer;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;

final class ListenerProviderWithContainerTest extends TestCase
{
    public function testGetListenersForEvent(): void
    {
        $container = $this->createMock(ContainerInterface::class);
        $container->method('get')
            ->withConsecutive(
                [ 'serviceA1' ],
                [ 'serviceA2' ],
                [ 'serviceB1' ],
                [ 'serviceB2' ]
            )->willReturnOnConsecutiveCalls(
                $a1 = function () {
                },
                $a2 = function () {
                },
                $b1 = function () {
                },
                $b2 = function () {
                }
            );

        $provider = new ListenerProviderWithContainer([

            SampleEventA::class => [ 'serviceA1', 'serviceA2' ],
            SampleEventB::class => [ 'serviceB1', 'serviceB2' ],
            SampleEventC::class => [ 'serviceC1', 'serviceC2' ],

        ], $container);

        $this->assertSame(
            [ $a1, $a2, $b1, $b2 ],
            iterable_to_array($provider->getListenersForEvent(new SampleEventB()))
        );
    }
}
