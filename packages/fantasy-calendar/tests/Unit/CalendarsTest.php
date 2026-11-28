<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\FantasyCalendar\Calendar;
use Tilttek\FantasyCalendar\Calendars;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Exception\OutOfRange;
use Tilttek\FantasyCalendar\Exception\UnknownCalendar;
use Tilttek\FantasyCalendar\Limits;
use Tilttek\FantasyCalendar\Link;
use Tilttek\FantasyCalendar\Month;

#[CoversClass(Calendars::class)]
#[CoversClass(Link::class)]
#[CoversClass(UnknownCalendar::class)]
final class CalendarsTest extends TestCase
{
    public static function simple(string $id): Calendar
    {
        return new Calendar($id, 'Calendar '.$id, [new Month('m', 'M', 30)], ['D']);
    }

    public static function registry(): Calendars
    {
        return Calendars::empty()
            ->with(self::simple('empire'))
            ->with(self::simple('city'))
            ->with(self::simple('guild'))
            ->with(self::simple('elves'))
            ->withParent(new Link(new Day('city', 0), new Day('empire', 1000)))
            ->withParent(new Link(new Day('guild', 10), new Day('city', 0)))
            ->linked(new Link(new Day('elves', 500), new Day('empire', 0)));
    }

    public function testDaysConvertThroughChainsOfLinks(): void
    {
        $registry = self::registry();

        self::assertSame(1000, $registry->convert(new Day('city', 0), 'empire')->number);
        self::assertSame('empire', $registry->convert(new Day('city', 0), 'empire')->calendar);
        self::assertSame(-10, $registry->convert(new Day('city', -20), 'guild')->number);
        self::assertSame(1500, $registry->convert(new Day('guild', 10), 'elves')->number);
        self::assertSame(-1490, $registry->convert(new Day('elves', 0), 'guild')->number);
        self::assertSame(7, $registry->convert(new Day('city', 7), 'city')->number);
    }

    public function testAncestors(): void
    {
        $registry = self::registry();

        self::assertSame(['city', 'empire'], $registry->ancestors('guild'));
        self::assertSame(['empire'], $registry->ancestors('city'));
        self::assertSame([], $registry->ancestors('elves'));
        self::assertSame('guild', $registry->get('guild')->id);
    }

    public function testAnUnknownCalendarIsRefused(): void
    {
        $this->expectException(UnknownCalendar::class);
        $this->expectExceptionMessageIsOrContains('No calendar "moon" is registered.');

        self::registry()->get('moon');
    }

    public function testConvertingToAnUnknownCalendarIsRefused(): void
    {
        $this->expectException(UnknownCalendar::class);
        $this->expectExceptionMessageIsOrContains('No calendar "moon" is registered.');

        self::registry()->convert(new Day('city', 0), 'moon');
    }

    public function testConvertingFromAnUnknownCalendarIsRefused(): void
    {
        $this->expectException(UnknownCalendar::class);
        $this->expectExceptionMessageIsOrContains('No calendar "moon" is registered.');

        self::registry()->convert(new Day('moon', 0), 'city');
    }

    public function testAncestorsOfAnUnknownCalendarAreRefused(): void
    {
        $this->expectException(UnknownCalendar::class);

        self::registry()->ancestors('moon');
    }

    public function testUnlinkedCalendarsDoNotConvert(): void
    {
        $registry = self::registry()->with(self::simple('dwarves'));

        $this->expectException(UnknownCalendar::class);
        $this->expectExceptionMessageIsOrContains('No chain of links joins the calendar "city" to the calendar "dwarves".');

        $registry->convert(new Day('city', 0), 'dwarves');
    }

    public function testAChainLongerThanTheBoundDoesNotConvert(): void
    {
        $registry = Calendars::empty()->with(self::simple('c0'));
        for ($i = 1; $i <= Limits::HOPS + 1; ++$i) {
            $registry = $registry->with(self::simple('c'.$i))->linked(new Link(new Day('c'.($i - 1), 0), new Day('c'.$i, 1)));
        }

        self::assertSame(Limits::HOPS, $registry->convert(new Day('c0', 0), 'c'.Limits::HOPS)->number);

        $this->expectException(UnknownCalendar::class);

        $registry->convert(new Day('c0', 0), 'c'.(Limits::HOPS + 1));
    }

    public function testACalendarIsRegisteredOnce(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('A calendar "city" is already registered.');

        self::registry()->with(self::simple('city'));
    }

    public function testARegistryIsBounded(): void
    {
        $registry = Calendars::empty();
        for ($i = 0; $i < Limits::CALENDARS; ++$i) {
            $registry = $registry->with(self::simple('c'.$i));
        }

        $this->expectException(OutOfRange::class);
        $this->expectExceptionMessageIsOrContains('A registry holds at most 256 calendars.');

        $registry->with(self::simple('one-more'));
    }

    public function testTwoCalendarsAreLinkedOnce(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The calendars "empire" and "city" are already linked.');

        self::registry()->linked(new Link(new Day('empire', 0), new Day('city', 0)));
    }

    public function testAChildHasOneParent(): void
    {
        $registry = self::registry()->with(self::simple('kingdom'));

        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The calendar "city" already has a parent.');

        $registry->withParent(new Link(new Day('city', 0), new Day('kingdom', 0)));
    }

    public function testACalendarIsNotItsOwnAncestor(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The calendar "guild" descends from "empire": it cannot be its parent.');

        self::registry()->withParent(new Link(new Day('empire', 0), new Day('guild', 3)));
    }

    public function testALinkJoinsTwoCalendars(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('A link joins two different calendars.');

        new Link(new Day('city', 0), new Day('city', 5));
    }

    public function testALinkHasAShift(): void
    {
        self::assertSame(-490, new Link(new Day('a', 500), new Day('b', 10))->shift());
    }
}
