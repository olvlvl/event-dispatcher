<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * An interface for Event Dispatchers that buffer Events.
 */
interface BufferedEventDispatcherInterface extends EventDispatcherInterface
{
    /**
     * Dispatch the buffered Events.
     *
     * @return object[] The Events dispatched.
     */
    public function dispatchBufferedEvents(): array;
}
