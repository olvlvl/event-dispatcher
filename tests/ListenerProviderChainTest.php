<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use olvlvl\EventDispatcher\ListenerProviderWithMap;
use olvlvl\EventDispatcher\ListenerProviderChain;
use PHPUnit\Framework\TestCase;

final class ListenerProviderChainTest extends TestCase
{
    public function testGetListenersForEvent(): void
    {
        $chain = new ListenerProviderChain([
            new ListenerProviderWithMap([
                SampleEventA::class => [
                    $f1 = function () {
                    },
                    $f2 = function () {
                    }
                ]
            ]),
            new ListenerProviderWithMap([
                SampleEventC::class => [
                    $f3 = function () {
                    }
                ]
            ])
        ]);

        $chain->appendListenerProviders(
            new ListenerProviderWithMap([
                SampleEventA::class => [
                    $f4 = function () {
                    }
                ]
            ]),
            new ListenerProviderWithMap([
                SampleEventA::class => [
                    $f5 = function () {
                    }
                ]
            ]),
            new ListenerProviderWithMap([
                SampleEventC::class => [
                    $f6 = function () {
                    }
                ]
            ])
        );

        $chain->prependListenerProviders(
            new ListenerProviderWithMap([
                SampleEventA::class => [
                    $f7 = function () {
                    },
                    $f8 = function () {
                    }
                ]
            ]),
            new ListenerProviderWithMap([
                SampleEventC::class => [
                    $f9 = function () {
                    }
                ]
            ])
        );

        $this->assertSame(
            [ $f7, $f8, $f1, $f2, $f4, $f5 ],
            iterable_to_array($chain->getListenersForEvent(new SampleEventA()))
        );

        $this->assertSame(
            [ $f9, $f3, $f6 ],
            iterable_to_array($chain->getListenersForEvent(new SampleEventC()))
        );
    }
}
