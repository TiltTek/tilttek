<?php

declare(strict_types=1);

namespace Tilttek\Progression\Season;

use DateTimeImmutable;
use DateTimeZone;
use Override;

/**
 * Seasons that follow the calendar without a gap, in a timezone: every month (`2026-11`), every quarter (`2026-q4`) or
 * every year (`2026`).
 */
final readonly class CalendarSeasons implements SeasonSchedule
{
    public function __construct(
        public SeasonLength $length,
        public DateTimeZone $timezone = new DateTimeZone('UTC'),
    ) {
    }

    #[Override]
    public function at(DateTimeImmutable $moment): Season
    {
        $local = $moment->setTimezone($this->timezone);
        $year = (int) $local->format('Y');
        $month = (int) $local->format('n');

        return match ($this->length) {
            SeasonLength::Month => $this->season(\sprintf('%04d-%02d', $year, $month), $year, $month, 1),
            SeasonLength::Quarter => $this->season(\sprintf('%04d-q%d', $year, intdiv($month + 2, 3)), $year, 3 * intdiv($month - 1, 3) + 1, 3),
            SeasonLength::Year => $this->season(\sprintf('%04d', $year), $year, 1, 12),
        };
    }

    #[Override]
    public function named(string $code): ?Season
    {
        $pattern = match ($this->length) {
            SeasonLength::Month => '/^(\d{4})-(0[1-9]|1[0-2])$/D',
            SeasonLength::Quarter => '/^(\d{4})-q([1-4])$/D',
            SeasonLength::Year => '/^(\d{4})()$/D',
        };

        if (1 !== preg_match($pattern, $code, $parts)) {
            return null;
        }

        // A month the season holds, as written in a date.
        $month = match ($this->length) {
            SeasonLength::Month => $parts[2],
            SeasonLength::Quarter => match ($parts[2]) {
                '1' => '03',
                '2' => '06',
                '3' => '09',
                default => '12',
            },
            SeasonLength::Year => '01',
        };

        return $this->at(new DateTimeImmutable(\sprintf('%s-%s-01', $parts[1], $month), $this->timezone));
    }

    private function season(string $code, int $year, int $month, int $months): Season
    {
        $start = new DateTimeImmutable(\sprintf('%04d-%02d-01', $year, $month), $this->timezone);

        return new Season($code, $start, $start->modify(\sprintf('+%d months', $months)));
    }
}
