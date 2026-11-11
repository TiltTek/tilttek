<?php

declare(strict_types=1);

namespace Tilttek\Quests\Schedule;

use DateTimeImmutable;
use Override;

/**
 * A quest that stays open.
 */
final readonly class Permanent implements Schedule
{
    #[Override]
    public function occurrenceAt(DateTimeImmutable $moment): Occurrence
    {
        return new Occurrence();
    }
}
