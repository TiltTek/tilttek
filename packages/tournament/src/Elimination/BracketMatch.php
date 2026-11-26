<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Elimination;

/**
 * A match of a bracket: its number, its round, its two places and the seeds of the first round.
 */
final readonly class BracketMatch
{
    public function __construct(
        public int $id,
        public int $round,
        public Slot $first,
        public Slot $second,
        public ?int $firstSeed = null,
        public ?int $secondSeed = null,
    ) {
    }
}
