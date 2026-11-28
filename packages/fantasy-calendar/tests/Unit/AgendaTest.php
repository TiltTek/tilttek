<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\FantasyCalendar\Agenda;
use Tilttek\FantasyCalendar\Calendar;
use Tilttek\FantasyCalendar\Calendars;
use Tilttek\FantasyCalendar\Contract\ReminderSource;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Exception\CalendarMismatch;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Exception\OutOfRange;
use Tilttek\FantasyCalendar\Link;
use Tilttek\FantasyCalendar\Month;
use Tilttek\FantasyCalendar\Occurrence;
use Tilttek\FantasyCalendar\Recurrence;
use Tilttek\FantasyCalendar\Reminder;
use Tilttek\FantasyCalendar\Store\InMemoryReminders;

#[CoversClass(Agenda::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(Reminder::class)]
#[CoversClass(InMemoryReminders::class)]
final class AgendaTest extends TestCase
{
    private function calendars(): Calendars
    {
        return Calendars::empty()
            ->with(CalendarTest::world())
            ->with(new Calendar('town', 'Town', [new Month('a', 'A', 10), new Month('b', 'B', 10)], ['D']))
            ->with(new Calendar('elsewhere', 'Elsewhere', [new Month('a', 'A', 10)], ['D']))
            ->withParent(new Link(new Day('town', 0), new Day('world', 100)))
            ->linked(new Link(new Day('elsewhere', 0), new Day('town', 0)));
    }

    /**
     * @param list<Occurrence> $occurrences
     *
     * @return list<string>
     */
    private function summary(array $occurrences): array
    {
        return array_map(static fn (Occurrence $o): string => $o->reminder->id.'@'.$o->day->calendar.':'.$o->day->number.($o->isInherited() ? '*' : ''), $occurrences);
    }

    public function testOwnAndInheritedRemindersOnceAndRecurring(): void
    {
        $store = new InMemoryReminders();
        $store->add(new Reminder('w1', 'battle', new Day('world', 103)));
        $store->add(new Reminder('t1', 'coronation', new Day('town', 3)));
        $store->add(new Reminder('t2', 'founding', new Day('town', 5), Recurrence::Yearly));
        $store->add(new Reminder('t3', 'market', new Day('town', 12), Recurrence::Monthly));
        $store->add(new Reminder('t4', 'later', new Day('town', 500)));
        $store->add(new Reminder('t5', 'gone', new Day('town', 4)));
        $store->add(new Reminder('x1', 'faraway', new Day('elsewhere', 3)));
        $store->remove('t5');

        $agenda = new Agenda($this->calendars(), $store);

        self::assertSame(
            ['t1@town:3', 'w1@town:3*', 't2@town:5', 't3@town:12', 't3@town:22', 't2@town:25'],
            $this->summary($agenda->between(new Day('town', 0), new Day('town', 25))),
        );
        self::assertSame(['t1@town:3', 'w1@town:3*'], $this->summary($agenda->between(new Day('town', 3), new Day('town', 3))));
        self::assertSame(['w1@world:103'], $this->summary($agenda->between(new Day('world', 103), new Day('world', 103))));
        self::assertSame(['x1@elsewhere:3'], $this->summary($agenda->between(new Day('elsewhere', 0), new Day('elsewhere', 9))));
    }

    public function testAYearlyReminderSkipsTheYearsWithoutItsDay(): void
    {
        $world = CalendarTest::world();
        $store = new InMemoryReminders();
        $store->add(new Reminder('leap', 'feast', $world->day(4, 6, 31), Recurrence::Yearly));
        $store->add(new Reminder('month', 'tithe', $world->day(4, 6, 31), Recurrence::Monthly));

        $occurrences = new Agenda($this->calendars(), $store)->between($world->day(5, 1, 1), $world->day(7, 7, 30));

        self::assertSame(['leap@world:'.$world->day(7, 6, 31)->number, 'month@world:'.$world->day(7, 6, 31)->number], $this->summary($occurrences));
    }

    public function testTheAgendaFiltersWhatTheSourceGives(): void
    {
        $source = new class implements ReminderSource {
            public function reminders(string $calendar, Day $from, Day $to): iterable
            {
                yield new Reminder('other', 'e', new Day('elsewhere', 3));
                yield new Reminder('after', 'e', new Day('town', 30));
                yield new Reminder('kept', 'e', new Day('town', 3));
            }
        };

        self::assertSame(['kept@town:3'], $this->summary(new Agenda($this->calendars(), $source)->between(new Day('town', 0), new Day('town', 9))));
    }

    public function testTheRangeIsBounded(): void
    {
        $agenda = new Agenda($this->calendars(), new InMemoryReminders());

        self::assertCount(0, $agenda->between(new Day('town', 0), new Day('town', 3659)));

        $this->expectException(OutOfRange::class);
        $this->expectExceptionMessageIsOrContains('An agenda lists 1 to 3660 days; this range has 3661.');

        $agenda->between(new Day('town', 0), new Day('town', 3660));
    }

    public function testTheRangeGoesForward(): void
    {
        $this->expectException(OutOfRange::class);
        $this->expectExceptionMessageIsOrContains('this range has 0.');

        new Agenda($this->calendars(), new InMemoryReminders())->between(new Day('town', 1), new Day('town', 0));
    }

    public function testTheRangeStaysInOneCalendar(): void
    {
        $this->expectException(CalendarMismatch::class);

        new Agenda($this->calendars(), new InMemoryReminders())->between(new Day('town', 1), new Day('world', 5));
    }

    public function testRemindersHaveIdentifiers(): void
    {
        $reminder = new Reminder('r', 'e', new Day('town', 1));

        self::assertSame(Recurrence::Once, $reminder->recurrence);
        self::assertSame('e', $reminder->entry);

        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('An entry identifier is 1 to 64');

        new Reminder('r', '', new Day('town', 1));
    }

    public function testReminderIdentifiersAreChecked(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('A reminder identifier is 1 to 64');

        new Reminder('r r', 'e', new Day('town', 1));
    }

    public function testTheStoreReturnsTheRemindersOfACalendarUpToTheEnd(): void
    {
        $store = new InMemoryReminders();
        $store->add(new Reminder('a', 'e', new Day('town', 1)));
        $store->add(new Reminder('b', 'e', new Day('town', 10)));
        $store->add(new Reminder('c', 'e', new Day('town', 11)));
        $store->add(new Reminder('d', 'e', new Day('world', 1)));

        $ids = array_map(static fn (Reminder $r): string => $r->id, iterator_to_array($store->reminders('town', new Day('town', 5), new Day('town', 10)), false));

        self::assertSame(['a', 'b'], $ids);
    }

    public function testTheSourceIsAskedForTheRangeOfEachCalendar(): void
    {
        $source = new class implements ReminderSource {
            /**
             * @var list<array{string, int, int}>
             */
            public array $asked = [];

            public function reminders(string $calendar, Day $from, Day $to): iterable
            {
                $this->asked[] = [$calendar, $from->number, $to->number];

                return [];
            }
        };

        new Agenda($this->calendars(), $source)->between(new Day('town', 3), new Day('town', 12));

        self::assertSame([['town', 3, 12], ['world', 103, 112]], $source->asked);
    }

    public function testARecurringReminderIsNotListedBesideTheRange(): void
    {
        $store = new InMemoryReminders();
        // Day 5 and day 15 of a town year of 20 days: the days next to the range from 6 to 14.
        $store->add(new Reminder('before', 'e', new Day('town', 5), Recurrence::Yearly));
        $store->add(new Reminder('after', 'e', new Day('town', -5), Recurrence::Yearly));

        self::assertSame([], $this->summary(new Agenda($this->calendars(), $store)->between(new Day('town', 6), new Day('town', 14))));
    }

    public function testOccurrencesFollowTheDayThenTheCalendarThenTheIdentifier(): void
    {
        $source = new class implements ReminderSource {
            public function reminders(string $calendar, Day $from, Day $to): iterable
            {
                if ('town' === $calendar) {
                    yield new Reminder('zeta', 'e', new Day('town', 3));
                    yield new Reminder('alpha', 'e', new Day('town', 3));
                    yield new Reminder('late', 'e', new Day('town', 5));
                    yield new Reminder('early', 'e', new Day('town', 2));
                }

                if ('world' === $calendar) {
                    yield new Reminder('aardvark', 'e', new Day('world', 103));
                }
            }
        };

        self::assertSame(
            ['early@town:2', 'alpha@town:3', 'zeta@town:3', 'aardvark@town:3*', 'late@town:5'],
            $this->summary(new Agenda($this->calendars(), $source)->between(new Day('town', 0), new Day('town', 9))),
        );
    }
}
