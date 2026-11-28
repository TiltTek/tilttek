<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * A reminder on one day of the calendar asked for — its own, or that of a parent calendar converted.
 */
final readonly class Occurrence
{
    public function __construct(public Reminder $reminder, public Day $day)
    {
    }

    /**
     * Whether the reminder belongs to a parent calendar rather than to the calendar asked for.
     */
    public function isInherited(): bool
    {
        return $this->reminder->day->calendar !== $this->day->calendar;
    }
}
