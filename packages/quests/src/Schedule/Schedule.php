<?php

declare(strict_types=1);

namespace Tilttek\Quests\Schedule;

use DateTimeImmutable;

/**
 * When a quest is open: always, between two dates, or at dates that come back, such as a week around a holiday each
 * year.
 */
interface Schedule
{
    /**
     * The occurrence a moment falls in, or null when the quest is closed then.
     */
    public function occurrenceAt(DateTimeImmutable $moment): ?Occurrence;
}
