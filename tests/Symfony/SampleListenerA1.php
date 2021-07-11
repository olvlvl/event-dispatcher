<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher\Symfony;

use tests\olvlvl\EventDispatcher\SampleEventA;

final class SampleListenerA1
{
    public function __invoke(SampleEventA $event): void
    {
    }
}
