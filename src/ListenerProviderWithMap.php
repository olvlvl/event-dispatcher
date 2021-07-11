<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * A simple listener provider, that's using a mapping of event to callables.
 */
final class ListenerProviderWithMap implements ListenerProviderInterface
{
    /**
     * @var array<class-string, callable[]>
     */
    private $listeners;

    /**
     * @param array<class-string, callable[]> $listeners
     */
    public function __construct(array $listeners)
    {
        $this->listeners = $listeners;
    }

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
}
