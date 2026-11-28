<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

/**
 * A reminder that ties an entry of the application — a character, a place, an event — to a day of a calendar, once
 * or every year or month from that day.
 */
final readonly class Reminder
{
    /**
     * @param string $entry the identifier of the entry, opaque to the library
     */
    public function __construct(public string $id, public string $entry, public Day $day, public Recurrence $recurrence = Recurrence::Once)
    {
        Limits::identifier($id, 'A reminder identifier');
        Limits::identifier($entry, 'An entry identifier');
    }
}
