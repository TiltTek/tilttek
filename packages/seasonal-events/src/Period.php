<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use DateTimeImmutable;

/**
 * One time a season comes: from the start of its first day, included, to the start of the day after its last, excluded,
 * in the season's timezone. Its code is the date of its first day, `2026-12-15`: it stays the same for that time
 * whatever the clock says, so that what a member earns in it can be told apart from what they earn the next year.
 */
final readonly class Period
{
    public function __construct(
        public string $code,
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
    ) {
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from && $moment < $this->until;
    }

    public function hasStarted(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from;
    }

    /**
     * The days the period lasts, counted on the calendar of its timezone.
     */
    public function days(): int
    {
        return Days::between($this->from, $this->until);
    }

    /**
     * @return array{code: string, from: string, until: string}
     */
    public function toArray(): array
    {
        return ['code' => $this->code, 'from' => $this->from->format(\DATE_ATOM), 'until' => $this->until->format(\DATE_ATOM)];
    }
}
