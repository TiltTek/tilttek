<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * Which dice count: keep or drop the highest or the lowest few.
 */
final readonly class Selection
{
    public function __construct(
        public SelectionMode $mode,
        public int $count,
    ) {
        if ($count < 1) {
            throw InvalidDefinition::because('A selection keeps or drops at least one die.');
        }
    }
}
