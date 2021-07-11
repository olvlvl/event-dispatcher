<?php

/*
 * (c) Olivier Laviale <olivier.laviale@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace tests\olvlvl\EventDispatcher;

/**
 * @param mixed[] $it
 *
 * @return mixed[]
 */
function iterable_to_array(iterable $it): array
{
    $ar = [];

    foreach ($it as $v) {
        $ar[] = $v;
    }

    return $ar;
}
