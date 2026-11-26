<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards;

/**
 * A member's own attendance: the distinct sessions, days and places they were admitted to — of one event, or of all —
 * and the weeks they came, in a row or not.
 */
final readonly class AttendanceSummary
{
    public function __construct(
        public int $sessions = 0,
        public int $days = 0,
        public int $places = 0,
        public int $weeks = 0,
    ) {
    }
}
