<?php

declare(strict_types=1);

namespace Tilttek\Progression\Season;

use DateTimeImmutable;

/**
 * The seasons of a track: which one a moment falls in — none between two seasons —, and which one a code names.
 */
interface SeasonSchedule
{
    public function at(DateTimeImmutable $moment): ?Season;

    public function named(string $code): ?Season;
}
