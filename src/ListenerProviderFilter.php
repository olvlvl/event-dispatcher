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
 * Decorates a Listener Provider to filter Listeners, according to a user specified discriminator.
 */
final class ListenerProviderFilter implements ListenerProviderInterface
{
    /**
     * @var ListenerProviderInterface
     */
    private $decorated;

    /**
     * @var callable
     * @phpstan-var callable(object $event, callable $listener):bool
     */
    private $discriminator;

    /**
     * @param ListenerProviderInterface $decorated
     * @param callable $discriminator Return `false` to discarded a listener.
     *
     * @phpstan-param callable(object $event, callable $listener):bool $discriminator
     */
    public function __construct(
        ListenerProviderInterface $decorated,
        callable $discriminator
    ) {
        $this->decorated = $decorated;
        $this->discriminator = $discriminator;
    }

    /**
     * @inheritDoc
     *
     * @return iterable<callable(object):void>
     */
    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->decorated->getListenersForEvent($event) as $listener) {
            if (($this->discriminator)($event, $listener)) {
                yield $listener;
            }
        }
    }
}
