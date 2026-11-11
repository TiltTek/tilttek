<?php

declare(strict_types=1);

namespace Tilttek\Quests\Progress;

use DateTimeImmutable;

/**
 * When a quest's last needed step was done, and by which activity.
 */
final readonly class Finish
{
    public function __construct(
        public DateTimeImmutable $at,
        public string $activity,
    ) {
    }
}
