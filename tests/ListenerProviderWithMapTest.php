<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use olvlvl\EventDispatcher\ListenerProviderWithMap;
use PHPUnit\Framework\TestCase;

final class ListenerProviderWithMapTest extends TestCase
{
    /**
     * @dataProvider provideGetListenersForEvent
     */
    public function testGetListenersForEvent(object $event, string $expected): void
    {
        $rc = '';
        $stu = new ListenerProviderWithMap([

            SampleEventA::class => [
                function () use (&$rc) {
                    $rc .= "f1";
                }
            ],
            SampleEventB::class => [
                function () use (&$rc) {
                    $rc .= "f2";
                }
            ],
            SampleEventInterface::class => [
                function () use (&$rc) {
                    $rc .= "f3";
                }
            ],

        ]);

        foreach ($stu->getListenersForEvent($event) as $listener) {
            $listener($event);
        }

        $this->assertSame($expected, $rc);
    }

    /**
     * @phpstan-ignore-next-line
     */
    public function provideGetListenersForEvent(): array
    {
        return [

            "match class" => [
                new SampleEventA(),
                "f1"
            ],

            "match inheritance" => [
                new SampleEventB(),
                "f1f2"

            ],

            "match interface" => [
                new SampleEventC(),
                "f3"
            ],

        ];
    }
}
