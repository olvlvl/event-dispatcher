<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\StoppableEventInterface;

/**
 * Decorates an Event Dispatcher to buffer events.
 *
 * Careful using this type of Dispatcher! Because the dispatching is delayed, it will cause issues for users that
 * expect Events to be modified.
 */
final class BufferedEventDispatcher implements BufferedEventDispatcherInterface
{
    /**
     * @var EventDispatcherInterface
     */
    private $decorated;

    /**
     * @var callable|null
     * @phpstan-var callable(object):bool|null
     */
    private $discriminator;

    /**
     * @var object[]
     */
    private $buffer = [];

    /**
     * @param EventDispatcherInterface $decorated
     * @param callable|null $discriminator Return `true` if the event should be buffered, `false` otherwise.
     *
     * @phpstan-param callable(object):bool|null $discriminator
     */
    public function __construct(
        EventDispatcherInterface $decorated,
        callable $discriminator = null
    ) {
        $this->decorated = $decorated;
        $this->discriminator = $discriminator;
    }

    /**
     * @inheritDoc
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            return $event;
        }

        if ($this->discriminator && !($this->discriminator)($event)) {
            return $this->decorated->dispatch($event);
        }

        $this->buffer[] = $event;

        return $event;
    }

    /**
     * @inheritDoc
     */
    public function dispatchBufferedEvents(): array
    {
        $buffer = $this->buffer;
        $this->buffer = [];

        foreach ($buffer as $event) {
            $this->decorated->dispatch($event);
        }

        // Since a Dispatcher MUST return the same Event object it was passed after it is done invoking Listeners,
        // the buffer can be returned as is.
        return $buffer;
    }
}
