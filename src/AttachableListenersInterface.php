<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use LogicException;

/**
 * An interface for a component that allows attaching listeners.
 */
interface AttachableListenersInterface
{
    /**
     * Add a Listener for an Event at the end of the list,
     * and return a callable that can be used to remove the Listener.
     *
     * @param class-string $eventType The class or interface of an Event.
     * @param callable(object):void $listener
     *
     * @return callable():void
     * @throws LogicException if the Listener is already defined for that Event.
     */
    public function appendListenerForEvent(string $eventType, callable $listener): callable;

    /**
     * Add a Listener for an Event at the beginning of the list,
     * and return a callable that can be used to remove the Listener.
     *
     * @param class-string $eventType The class or interface of an Event.
     * @param callable(object):void $listener
     *
     * @return callable():void
     * @throws LogicException if the Listener is already defined for that Event.
     */
    public function prependListenerForEvent(string $eventType, callable $listener): callable;
}
