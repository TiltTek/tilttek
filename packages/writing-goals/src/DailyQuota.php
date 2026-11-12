<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

/**
 * The words — or pages — a writing day of the plan asks for.
 */
final readonly class DailyQuota
{
    public function __construct(
        public Day $day,
        public int $quota,
    ) {
    }
}
