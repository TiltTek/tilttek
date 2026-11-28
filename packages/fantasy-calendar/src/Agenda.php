<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Contract\ReminderSource;
use Tilttek\FantasyCalendar\Exception\CalendarMismatch;
use Tilttek\FantasyCalendar\Exception\OutOfRange;
use Tilttek\FantasyCalendar\Exception\UnknownCalendar;

/**
 * The reminders that fall in a range of days of a calendar: its own, and those of its parents converted, once or
 * recurring. Keeps no state between two calls.
 */
final readonly class Agenda
{
    public function __construct(private Calendars $calendars, private ReminderSource $reminders)
    {
    }

    /**
     * Every reminder from one day to another, both included, by day, then the calendar's own before those of its
     * parents, then by reminder identifier.
     *
     * @return list<Occurrence>
     *
     * @throws CalendarMismatch when the two days belong to different calendars
     * @throws OutOfRange       when the range is longer than {@see Limits::RANGE} days, or out of the bounds of the
     *                          years
     * @throws UnknownCalendar  when the calendar or a parent is not registered, or not linked
     */
    public function between(Day $from, Day $to): array
    {
        $length = $to->daysSince($from) + 1;
        if ($length < 1 || $length > Limits::RANGE) {
            throw OutOfRange::because(\sprintf('An agenda lists 1 to %d days; this range has %d.', Limits::RANGE, $length));
        }

        $occurrences = [];
        foreach ([$from->calendar, ...$this->calendars->ancestors($from->calendar)] as $rank => $id) {
            $calendar = $this->calendars->get($id);
            $start = $this->calendars->convert($from, $id);
            $end = $start->plus($length - 1);
            $once = [];
            $yearly = [];
            $monthly = [];
            foreach ($this->reminders->reminders($id, $start, $end) as $reminder) {
                if ($reminder->day->calendar !== $id || $reminder->day->isAfter($end)) {
                    continue;
                }

                $date = $calendar->date($reminder->day);
                match ($reminder->recurrence) {
                    Recurrence::Once => $once[$reminder->day->number][] = $reminder,
                    Recurrence::Yearly => $yearly[$date->month.'-'.$date->day][] = $reminder,
                    Recurrence::Monthly => $monthly[$date->day][] = $reminder,
                };
            }

            for ($offset = 0; $offset < $length; ++$offset) {
                $day = $start->plus($offset);
                $date = $calendar->date($day);
                $found = [
                    ...$once[$day->number] ?? [],
                    ...$yearly[$date->month.'-'.$date->day] ?? [],
                    ...$monthly[$date->day] ?? [],
                ];
                foreach ($found as $reminder) {
                    if (!$reminder->day->isAfter($day)) {
                        $occurrences[] = [$offset, $rank, $reminder->id, new Occurrence($reminder, $from->plus($offset))];
                    }
                }
            }
        }

        usort($occurrences, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);

        return array_map(static fn (array $entry): Occurrence => $entry[3], $occurrences);
    }
}
