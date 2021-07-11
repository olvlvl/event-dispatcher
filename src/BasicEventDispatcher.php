<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * A basic implementation of an Event Dispatcher.
 */
final class BasicEventDispatcher implements EventDispatcherInterface
{
    /**
     * @var ListenerProviderInterface
     */
    private $listenerProvider;

    public function __construct(ListenerProviderInterface $listenerProvider)
    {
        $this->listenerProvider = $listenerProvider;
    }

    /**
     * @inheritDoc
     */
    public function dispatch(object $event): object
    {
        $stoppable = $event instanceof StoppableEventInterface;
        if ($stoppable && $event->isPropagationStopped()) {
            return $event;
        }

        foreach ($this->listenerProvider->getListenersForEvent($event) as $listener) {
            $listener($event);

            // @phpstan-ignore-next-line
            if ($stoppable && $event->isPropagationStopped()) {
                break;
            }
        }

        return $event;
    }
}
