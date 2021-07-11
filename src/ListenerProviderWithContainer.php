<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;

/**
 * A Listener Provider that uses a PSR container to retrieve Listeners.
 */
final class ListenerProviderWithContainer implements ListenerProviderInterface
{
    /**
     * @var array<class-string, string[]>
     */
    private $listeners;

    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @param array<class-string, string[]> $listeners
     */
    public function __construct(array $listeners, ContainerInterface $container)
    {
        $this->listeners = $listeners;
        $this->container = $container;
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
                    yield $this->container->get($listener);
                }
            }
        }
    }
}
