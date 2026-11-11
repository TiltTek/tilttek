<?php

declare(strict_types=1);

namespace Tilttek\Quests\Schedule;

use DateTimeZone;
use Tilttek\Quests\Exception\InvalidQuest;

/**
 * Reads a schedule of its own from the keys it knows in a quest's definition — dates that come back each year, for
 * instance —, for `QuestReader`.
 */
interface ScheduleReader
{
    /**
     * The keys of a definition it reads.
     *
     * @return list<string>
     */
    public function keys(): array;

    /**
     * The schedule the definition's keys describe, or null when none of its keys is there.
     *
     * @param array<array-key, mixed> $definition
     *
     * @throws InvalidQuest
     */
    public function read(array $definition, DateTimeZone $timezone): ?Schedule;
}
