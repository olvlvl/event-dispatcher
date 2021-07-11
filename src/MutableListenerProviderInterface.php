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
 * An interface for a Listener Provider that can be mutated.
 */
interface MutableListenerProviderInterface extends ListenerProviderInterface, AttachableListenersInterface
{
}
