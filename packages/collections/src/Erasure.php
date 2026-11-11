<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * What erasing a member removed: their copies, the sets they completed, the packs they opened and the dice they chose.
 */
final readonly class Erasure
{
    public function __construct(
        public int $copies,
        public int $completions,
        public int $openings,
        public int $choices,
    ) {
    }
}
