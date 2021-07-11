<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use LogicException;
use olvlvl\EventDispatcher\MutableListenerProvider;
use PHPUnit\Framework\TestCase;

final class MutableListenerProviderTest extends TestCase
{
    public function testAppendListenerForEventFailsOnDuplicateListener(): void
    {
        $listener = function () {
        };
        $stu = new MutableListenerProvider();
        $stu->appendListenerForEvent(SampleEventA::class, $listener);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches("/Listener already defined/");
        $stu->appendListenerForEvent(SampleEventA::class, $listener);
    }

    public function testPrependListenerForEventFailsOnDuplicateListener(): void
    {
        $listener = function () {
        };
        $stu = new MutableListenerProvider();
        $stu->prependListenerForEvent(SampleEventA::class, $listener);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches("/Listener already defined/");
        $stu->prependListenerForEvent(SampleEventA::class, $listener);
    }

    public function testAppendPrependRemove(): void
    {
        $stu = new MutableListenerProvider();
        $r1 = $stu->appendListenerForEvent(SampleEventA::class, $l1 = function () {
        });
        $r2 = $stu->prependListenerForEvent(SampleEventA::class, $l2 = function () {
        });
        $r3 = $stu->prependListenerForEvent(SampleEventA::class, $l3 = function () {
        });
        $r4 = $stu->appendListenerForEvent(SampleEventA::class, $l4 = function () {
        });
        $stu->appendListenerForEvent(SampleEventC::class, function () {
        }); // Trap
        $event = new SampleEventA();

        $this->assertSame(
            [ $l3, $l2, $l1, $l4 ],
            iterable_to_array($stu->getListenersForEvent($event))
        );

        $r2();
        $r2(); // called twice, shouldn't matter
        $this->assertSame(
            [ $l3, $l1, $l4 ],
            iterable_to_array($stu->getListenersForEvent($event))
        );

        $r1();
        $this->assertSame(
            [ $l3, $l4 ],
            iterable_to_array($stu->getListenersForEvent($event))
        );

        $r3();
        $this->assertSame(
            [ $l4 ],
            iterable_to_array($stu->getListenersForEvent($event))
        );

        $r4();
        $this->assertSame(
            [],
            iterable_to_array($stu->getListenersForEvent($event))
        );
    }

    /**
     * @dataProvider provideGetListenersForEvent
     */
    public function testGetListenersForEvent(object $event, string $expected): void
    {
        $rc = '';
        $stu = new MutableListenerProvider();
        $stu->appendListenerForEvent(SampleEventA::class, function () use (&$rc) {
            $rc .= "f1";
        });
        $stu->appendListenerForEvent(SampleEventB::class, function () use (&$rc) {
            $rc .= "f2";
        });
        $stu->appendListenerForEvent(SampleEventInterface::class, function () use (&$rc) {
            $rc .= "f3";
        });

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
                "f1",
            ],

            "match inheritance" => [
                new SampleEventB(),
                "f1f2",
            ],

            "match interface" => [
                new SampleEventC(),
                "f3",
            ],

        ];
    }
}
