<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\FantasyCalendar\Calendar;
use Tilttek\FantasyCalendar\CalendarCodec;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\LeapRule;
use Tilttek\FantasyCalendar\Limits;
use Tilttek\FantasyCalendar\Moon;
use Tilttek\FantasyCalendar\Phase;
use Tilttek\FantasyCalendar\Season;
use Tilttek\FantasyCalendar\Template\Gregorian;

/**
 * The Gregorian model against PHP's own dates, and the properties of the conversions: from a date to its day and back,
 * from a day to its date and back, over a million years on either side of year 0.
 */
#[CoversClass(Gregorian::class)]
#[CoversClass(Calendar::class)]
#[CoversClass(LeapRule::class)]
final class GregorianTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach ([1, 7, 42, 1999, 31337] as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    #[DataProvider('seeds')]
    public function testDaysAndWeekdaysMatchPhpDates(int $seed): void
    {
        $calendar = Gregorian::calendar();
        $chance = new Randomizer(new Mt19937($seed));
        $epoch = new DateTimeImmutable('0001-01-01');
        for ($i = 0; $i < 300; ++$i) {
            $year = $chance->getInt(1, 9999);
            $month = $chance->getInt(1, 12);
            $dayOfMonth = $chance->getInt(1, $calendar->monthLength($year, $month));
            $php = new DateTimeImmutable(\sprintf('%04d-%02d-%02d', $year, $month, $dayOfMonth));
            $day = $calendar->day($year, $month, $dayOfMonth);

            self::assertSame($epoch->diff($php)->days, $day->number);
            self::assertSame((int) $php->format('N') - 1, $calendar->weekday($day));
            self::assertSame($php->format('l'), $calendar->weekdayName($day));
            $date = $calendar->date($day);
            self::assertSame([$year, $month, $dayOfMonth], [$date->year, $date->month, $date->day]);
        }
    }

    #[DataProvider('seeds')]
    public function testDaysGoToDatesAndBack(int $seed): void
    {
        $calendar = Gregorian::calendar();
        $chance = new Randomizer(new Mt19937($seed));
        for ($i = 0; $i < 500; ++$i) {
            $number = $chance->getInt(-365_242_000, 365_242_000);
            $date = $calendar->date(new Day('gregorian', $number));
            self::assertSame($number, $calendar->dayOf($date)->number);
            self::assertSame($number + 1, $calendar->dayOf($calendar->date(new Day('gregorian', $number + 1)))->number);
            self::assertGreaterThanOrEqual(1, $date->day);
            self::assertLessThanOrEqual($calendar->monthLength($date->year, $date->month), $date->day);
        }
    }

    public function testTheBoundsOfTheYears(): void
    {
        $calendar = Gregorian::calendar();
        $first = $calendar->day(-Limits::YEAR, 1, 1);
        $last = $calendar->day(Limits::YEAR, 12, 31);

        self::assertSame(-Limits::YEAR, $calendar->date($first)->year);
        self::assertSame(Limits::YEAR, $calendar->date($last)->year);
        self::assertSame([12, 31], [$calendar->date($last)->month, $calendar->date($last)->day]);
        self::assertSame([1, 1], [$calendar->date($first)->month, $calendar->date($first)->day]);
    }

    public function testLeapYears(): void
    {
        $calendar = Gregorian::calendar();

        self::assertTrue($calendar->isLeap(2000));
        self::assertTrue($calendar->isLeap(2024));
        self::assertFalse($calendar->isLeap(1900));
        self::assertFalse($calendar->isLeap(2023));
        self::assertTrue($calendar->isLeap(0));
        self::assertTrue($calendar->isLeap(-4));
        self::assertFalse($calendar->isLeap(-100));
        self::assertSame(366, $calendar->yearLength(2000));
        self::assertSame(365, $calendar->yearLength(1900));
        self::assertSame(29, $calendar->monthLength(2024, 2));
        self::assertSame(28, $calendar->monthLength(2023, 2));
        self::assertSame(-366, $calendar->day(0, 1, 1)->number);
    }

    public function testTheMoon(): void
    {
        $calendar = Gregorian::calendar();

        self::assertSame(Phase::New, $calendar->moonPhases($calendar->day(2000, 1, 6))['moon']);
        self::assertSame(Phase::Full, $calendar->moonPhases($calendar->day(2000, 1, 21))['moon']);
        self::assertSame(Phase::FirstQuarter, $calendar->moonPhases($calendar->day(2000, 1, 14))['moon']);
        self::assertSame(Phase::Full, $calendar->moonPhases($calendar->day(2026, 11, 24))['moon']);
        $moon = $calendar->moons[0];
        self::assertInstanceOf(Moon::class, $moon);
        self::assertSame('29.530588', $moon->decimalCycle());
    }

    public function testErasAndSeasons(): void
    {
        $calendar = Gregorian::calendar();

        self::assertSame(['bce', 1], [$calendar->yearInEra(0)->era?->id, $calendar->yearInEra(0)->year]);
        self::assertSame(['ce', 2026], [$calendar->yearInEra(2026)->era?->id, $calendar->yearInEra(2026)->year]);
        self::assertSame(-43, $calendar->yearFromEra('bce', 44));
        $winter = $calendar->seasonOn($calendar->day(2026, 1, 15));
        self::assertNotNull($winter);
        self::assertSame(['winter', 26, 89], [$winter->season->id, $winter->day, $winter->length]);
    }

    public function testTheDefinitionIsReadyToCopy(): void
    {
        $definition = Gregorian::definition('my-world');
        $calendar = CalendarCodec::fromArray($definition);

        self::assertSame('my-world', $calendar->id);
        self::assertSame($definition, CalendarCodec::toArray($calendar));
        self::assertSame('gregorian', Gregorian::calendar()->id);
        self::assertSame('earth', Gregorian::calendar('earth')->id);
    }

    public function testTheCalendarKeepsItsAstronomicalAnchors(): void
    {
        $calendar = Gregorian::calendar();

        self::assertSame(730_124, $calendar->moons[0]->newMoon);
        self::assertSame([20, 21, 22, 21], array_map(static fn (Season $season): int => $season->day, $calendar->seasons));
    }
}
