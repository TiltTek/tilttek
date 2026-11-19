<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * The result of a team that escaped: its final time and what it is made of, and whether the team agreed to show it.
 */
final readonly class RoomResult
{
    /**
     * @param int  $finalSeconds the seconds played plus the penalties
     * @param bool $public       whether the team agreed to show it on the public leaderboard
     */
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public int $finalSeconds,
        public int $playedSeconds,
        public int $penaltySeconds,
        public int $hints,
        public int $skips,
        public DateTimeImmutable $escapedAt,
        public bool $public,
    ) {
    }
}
