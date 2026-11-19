<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\GameOutcome;

/**
 * A game over: escaped, out of time or abandoned, with its final time. The moment a page celebrates an escape. It names identifiers only.
 */
final readonly class GameFinished
{
    /**
     * @param int $finalSeconds the time played, within the duration of the room, plus the penalties
     */
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public GameOutcome $outcome,
        public int $finalSeconds,
        public DateTimeImmutable $at,
    ) {
    }
}
