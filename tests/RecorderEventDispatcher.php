<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

final class RecorderEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var object[]
     */
    public $events = [];

    /**
     * @inheritDoc
     */
    public function dispatch(object $event): object
    {
        $this->events[] = $event;

        return $event;
    }
}
