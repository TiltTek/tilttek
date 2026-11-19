<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A puzzle the host marked solved for players who were stuck. It names identifiers only.
 */
final readonly class PuzzleSkipped
{
    /**
     * @param int $penalty the seconds it adds to the final time
     */
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public string $puzzle,
        public int $penalty,
        public ResourceReference $host,
        public DateTimeImmutable $at,
    ) {
    }
}
