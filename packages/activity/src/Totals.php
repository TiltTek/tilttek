<?php

declare(strict_types=1);

namespace Tilttek\Activity;

/**
 * How many activities match, and the sum of their quantities, cancelled activities left out.
 */
final readonly class Totals
{
    public function __construct(
        public int $count = 0,
        public int $quantity = 0,
    ) {
    }
}
