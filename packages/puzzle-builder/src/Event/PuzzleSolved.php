<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A puzzle solved, by its answer or skipped by the host: the moment a screen celebrates, or a room opens
 * its next door. Dispatched once the move is made; it names identifiers only.
 */
final readonly class PuzzleSolved
{
    /**
     * @param bool $skipped whether the host skipped it for players who were stuck
     */
    public function __construct(
        public string $scenario,
        public ResourceReference $team,
        public string $puzzle,
        public DateTimeImmutable $at,
        public bool $skipped,
    ) {
    }
}
