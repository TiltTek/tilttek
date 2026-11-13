<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A tier of hints revealed, asked by the players or pushed by the host: what a hint screen shows. Dispatched once the move is made; it names identifiers only.
 */
final readonly class HintRevealed
{
    /**
     * @param int  $tier   from 1, the first hint of the puzzle
     * @param bool $pushed whether the host pushed it
     */
    public function __construct(
        public string $scenario,
        public ResourceReference $team,
        public string $puzzle,
        public DateTimeImmutable $at,
        public int $tier,
        public bool $pushed,
    ) {
    }
}
