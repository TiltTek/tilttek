<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * How many copies of an item a member owns; those beyond the first are doubles.
 */
final readonly class Holding
{
    public function __construct(
        public Item $item,
        public int $count,
    ) {
    }

    public function doubles(): int
    {
        return $this->count - 1;
    }
}
