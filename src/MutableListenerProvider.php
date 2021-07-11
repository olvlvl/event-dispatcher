<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use LogicException;

use function array_search;
use function array_unshift;
use function in_array;

final class MutableListenerProvider implements MutableListenerProviderInterface
{
    /**
     * @var array<class-string, array<int, callable>>
     */
    private $listeners = [];

    /**
     * @inheritDoc
     *
     * @return iterable<callable(object):void>
     */
    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->listeners as $class => $listeners) {
            if ($event instanceof $class) {
                foreach ($listeners as $listener) {
                    yield $listener;
                }
            }
        }
    }

    /**
     * @inheritDoc
     */
    public function appendListenerForEvent(string $eventType, callable $listener): callable
    {
        $this->assertUnique($eventType, $listener);

        $this->listeners[$eventType][] = $listener;

        return $this->makeRemoveCallback($eventType, $listener);
    }

    /**
     * @inheritDoc
     */
    public function prependListenerForEvent(string $eventType, callable $listener): callable
    {
        $this->assertUnique($eventType, $listener);

        if (!isset($this->listeners[$eventType])) {
            $this->listeners[$eventType] = [];
        }

        array_unshift($this->listeners[$eventType], $listener);

        return $this->makeRemoveCallback($eventType, $listener);
    }

    private function assertUnique(string $eventType, callable $listener): void
    {
        $listeners = $this->listeners[$eventType] ?? null;

        if (!$listeners) {
            return;
        }

        if (in_array($listener, $listeners, true)) {
            throw new LogicException("Listener already defined for event type '$eventType'.");
        }
    }

    private function makeRemoveCallback(string $eventType, callable $listener): callable
    {
        return function () use ($eventType, $listener) {
            $key = array_search($listener, $this->listeners[$eventType], true);

            if ($key === false) {
                // The Listener has already been removed.
                // It's not great that the user is removing the listener twice,
                // but it's not critical since the result is the same.
                return;
            }

            unset($this->listeners[$eventType][$key]);
        };
    }
}
