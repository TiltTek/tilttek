<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Fixture;

use DateTimeImmutable;
use DateTimeZone;
use Override;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Schedule\Schedule;

/**
 * A quest that comes back each calendar year, in UTC.
 */
final readonly class Yearly implements Schedule
{
    #[Override]
    public function occurrenceAt(DateTimeImmutable $moment): Occurrence
    {
        $year = $moment->setTimezone(new DateTimeZone('UTC'))->format('Y');
        $opens = new DateTimeImmutable($year.'-01-01 UTC');

        return new Occurrence($year, $opens, $opens->modify('+1 year'));
    }
}
