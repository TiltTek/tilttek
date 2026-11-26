<?php

declare(strict_types=1);

namespace Tilttek\RpgCalendarEvents;

use DateTimeImmutable;
use Phil\Almanac\DateSpan;

/**
 * One date the catalog computed: the entry, with its source, and the days it covers that year.
 */
final readonly class Occurrence
{
    public function __construct(
        public Entry $entry,
        public DateSpan $span,
    ) {
    }

    /**
     * The midnight of its first day, in the catalog's time zone.
     */
    public function start(): DateTimeImmutable
    {
        return $this->span->start;
    }

    /**
     * The midnight of its last day: its first for a date of one day.
     */
    public function end(): DateTimeImmutable
    {
        return $this->span->end;
    }

    /**
     * Where its rule comes from.
     */
    public function source(): string
    {
        return $this->entry->source;
    }
}
