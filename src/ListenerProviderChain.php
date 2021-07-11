<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace olvlvl\EventDispatcher;

use Psr\EventDispatcher\ListenerProviderInterface;

use function array_push;
use function array_unshift;

/**
 * A chain of Listener Providers.
 *
 * Listener Providers are called in succession to provide Listeners for an Event.
 */
final class ListenerProviderChain implements ListenerProviderInterface
{
    /**
     * @var ListenerProviderInterface[]
     */
    private $providers = [];

    /**
     * @param ListenerProviderInterface[] $providers
     */
    public function __construct(iterable $providers = [])
    {
        $this->appendListenerProviders(...$providers);
    }

    /**
     * Add Listener Providers to the end of the chain.
     *
     * Note: Listener Providers are appended to the chain just like `array_push` append values to an array.
     */
    public function appendListenerProviders(ListenerProviderInterface ...$providers): void
    {
        array_push($this->providers, ...$providers);
    }

    /**
     * Add Listener Providers to the beginning of the chain.
     *
     * Note: Listener Providers are prepended to the chain just like `array_unshift` prepend values to an array.
     */
    public function prependListenerProviders(ListenerProviderInterface ...$providers): void
    {
        array_unshift($this->providers, ...$providers);
    }

    /**
     * @inheritDoc
     *
     * @return iterable<callable(object):void>
     */
    public function getListenersForEvent(object $event): iterable
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->getListenersForEvent($event) as $listener) {
                yield $listener;
            }
        }
    }
}
