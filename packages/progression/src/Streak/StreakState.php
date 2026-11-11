<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use Phil\Resource\ResourceReference;

/**
 * What is kept of a member's streak between two gestures, to tell when it breaks: the run it was on — 0 once broken
 * —, the best run, the unit the run started and the last active one, and the timezone of the member's last gesture.
 */
final readonly class StreakState
{
    public function __construct(
        public string $streak,
        public ResourceReference $member,
        public int $current,
        public int $best,
        public ?int $start,
        public int $lastActive,
        public string $timezone,
    ) {
    }

    public function isAlive(): bool
    {
        return 0 < $this->current;
    }
}
