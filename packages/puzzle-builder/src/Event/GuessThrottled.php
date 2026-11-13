<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A guess not checked: the team made too many on the puzzle in the period. A log of it shows a script trying every
 * word. It names identifiers only.
 */
final readonly class GuessThrottled
{
    public function __construct(
        public string $scenario,
        public ResourceReference $team,
        public string $puzzle,
        public DateTimeImmutable $retryAfter,
    ) {
    }
}
