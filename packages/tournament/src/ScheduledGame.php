<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use DateTimeImmutable;

/**
 * A round in a participant's schedule: the stage, the round and its date when set, the pool, the opponent and the side,
 * or a bye or a rest, and whether the round is published or only foreseen by the Berger table of the pool.
 */
final readonly class ScheduledGame
{
    public function __construct(
        public string $stage,
        public int $round,
        public ?string $opponent,
        public bool $first,
        public bool $rest = false,
        public ?string $pool = null,
        public ?DateTimeImmutable $startsAt = null,
        public bool $published = true,
    ) {
    }

    /**
     * Whether the participant has no opponent this round: a bye, or a rest.
     */
    public function isBye(): bool
    {
        return null === $this->opponent;
    }
}
