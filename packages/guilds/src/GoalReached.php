<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;

/**
 * A guild reached the target of one of its goals: identifiers only — the goal, the guild —, the points gathered and
 * the moment.
 */
final readonly class GoalReached
{
    public function __construct(
        public string $goal,
        public string $guild,
        public int $points,
        public DateTimeImmutable $at,
    ) {
    }
}
