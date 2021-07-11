<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

use Psr\EventDispatcher\StoppableEventInterface;

class SampleStoppableEvent implements StoppableEventInterface
{
    /**
     * @var bool;
     */
    public $stopped;

    public function __construct(bool $stopped = false)
    {
        $this->stopped = $stopped;
    }

    public function isPropagationStopped(): bool
    {
        return $this->stopped;
    }
}
