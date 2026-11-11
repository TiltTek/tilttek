<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A member's streak broke: the run it had reached, the last unit the member was active, and when it was seen.
 */
final readonly class StreakBroken
{
    public function __construct(
        public string $streak,
        public ResourceReference $member,
        public int $length,
        public int $lastActive,
        public DateTimeImmutable $at,
    ) {
    }
}
