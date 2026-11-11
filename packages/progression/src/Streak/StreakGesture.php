<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * An activity that made a day — or a week — of a member's streak active: one per streak and activity.
 */
final readonly class StreakGesture
{
    public function __construct(
        public string $streak,
        public ResourceReference $member,
        public string $activity,
        public int $unit,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
