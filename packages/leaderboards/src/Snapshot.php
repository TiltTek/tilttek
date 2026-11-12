<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;

/**
 * A board frozen at the end of a period, to show it and reward the first: the first members with their ranks and
 * scores — as many as the board keeps —, how many were ranked, and when it was taken. It stores who the members are;
 * whether a viewer sees them is decided when it is shown, by their choice then.
 */
final readonly class Snapshot
{
    /**
     * @param list<Placing> $placings in rank order
     */
    public function __construct(
        public string $board,
        public string $period,
        public DateTimeImmutable $takenAt,
        public int $participants,
        public array $placings,
    ) {
    }
}
