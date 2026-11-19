<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

/**
 * The clock of a game at one moment, in whole seconds: what the countdown and the hint screen show.
 *
 * The penalties of the hints and of the puzzles skipped are added to the final time; they are not taken from the
 * countdown.
 */
final readonly class Timer
{
    /**
     * @param int $played    the seconds played, pauses left out, within the duration of the room
     * @param int $remaining the seconds left on the countdown
     * @param int $penalty   the seconds the hints and the puzzles skipped add
     * @param int $hints     the hints revealed, asked or pushed
     * @param int $skips     the puzzles the host skipped
     */
    public function __construct(
        public int $played,
        public int $remaining,
        public int $penalty,
        public int $hints,
        public int $skips,
        public bool $paused,
    ) {
    }

    public function timeUp(): bool
    {
        return 0 === $this->remaining;
    }

    /**
     * The time that ranks a team: the seconds played plus the penalties.
     */
    public function final(): int
    {
        return $this->played + $this->penalty;
    }
}
