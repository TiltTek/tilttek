<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Benchmark;

use Tilttek\FantasyCalendar\Agenda;
use Tilttek\FantasyCalendar\Calendar;
use Tilttek\FantasyCalendar\Calendars;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Recurrence;
use Tilttek\FantasyCalendar\Reminder;
use Tilttek\FantasyCalendar\Store\InMemoryReminders;
use Tilttek\FantasyCalendar\Template\Gregorian;

/**
 * What a calendar page does: turn a thousand days far from year 1 into dates with their weekday, moon and season, and
 * list the reminders of the longest range an agenda allows.
 */
final readonly class CalendarBench
{
    private Calendar $calendar;

    private Agenda $agenda;

    public function __construct()
    {
        $this->calendar = Gregorian::calendar();
        $store = new InMemoryReminders();
        for ($i = 0; $i < 200; ++$i) {
            $store->add(new Reminder('r'.$i, 'e'.$i, new Day('gregorian', 700_000 + $i * 37), 0 === $i % 3 ? Recurrence::Yearly : Recurrence::Once));
        }

        $this->agenda = new Agenda(Calendars::empty()->with($this->calendar), $store);
    }

    public function benchOneThousandDates(): void
    {
        for ($i = 0; $i < 1_000; ++$i) {
            $day = new Day('gregorian', 300_000_000 + $i * 997);
            $this->calendar->date($day);
            $this->calendar->weekday($day);
            $this->calendar->moonPhases($day);
            $this->calendar->seasonOn($day);
        }
    }

    public function benchTheLongestAgenda(): void
    {
        $this->agenda->between(new Day('gregorian', 700_000), new Day('gregorian', 703_659));
    }
}
