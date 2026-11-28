<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\FantasyCalendar\Calendar;
use Tilttek\FantasyCalendar\Date;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Era;
use Tilttek\FantasyCalendar\EraYear;
use Tilttek\FantasyCalendar\Exception\CalendarMismatch;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Exception\OutOfRange;
use Tilttek\FantasyCalendar\LeapCycle;
use Tilttek\FantasyCalendar\LeapRule;
use Tilttek\FantasyCalendar\Limits;
use Tilttek\FantasyCalendar\Month;
use Tilttek\FantasyCalendar\Moon;
use Tilttek\FantasyCalendar\Phase;
use Tilttek\FantasyCalendar\Season;
use Tilttek\FantasyCalendar\SeasonAt;

#[CoversClass(Calendar::class)]
#[CoversClass(EraYear::class)]
#[CoversClass(SeasonAt::class)]
final class CalendarTest extends TestCase
{
    public static function world(): Calendar
    {
        return new Calendar(
            'world',
            'Reckoning of the Vale',
            [
                new Month('frost', 'Frost', 30),
                new Month('thaw', 'Thaw', 30),
                new Month('midfeast', 'Midfeast', 0, 1, false),
                new Month('bloom', 'Bloom', 30),
                new Month('blaze', 'Blaze', 30),
                new Month('harvest', 'Harvest', 30, 1),
                new Month('fall', 'Fall', 30),
            ],
            ['Oneday', 'Twoday', 'Threeday', 'Fourday', 'Fiveday', 'Sixday'],
            2,
            new LeapRule([new LeapCycle(3, 1), new LeapCycle(9, 1, false)]),
            [new Moon('pale', 'Pale', 8, 1, 3), Moon::ofDecimalCycle('red', 'Red', '12.5')],
            [new Era('before', 'Before the Founding', null), new Era('founding', 'Founding', 1), new Era('kings', 'Kings', 300)],
            [new Season('cold', 'Cold', 1, 1), new Season('warm', 'Warm', 4, 1)],
            'temperate',
        );
    }

    public function testLeapYearsAddTheirDays(): void
    {
        $world = self::world();

        self::assertFalse($world->isLeap(1));
        self::assertTrue($world->isLeap(4));
        self::assertTrue($world->isLeap(7));
        self::assertFalse($world->isLeap(10));
        self::assertTrue($world->isLeap(-2));
        self::assertSame(180, $world->yearLength(1));
        self::assertSame(182, $world->yearLength(4));
        self::assertSame(1, $world->monthLength(4, 3));
        self::assertSame(0, $world->monthLength(1, 3));
        self::assertSame(31, $world->monthLength(4, 6));
        self::assertSame('midfeast', $world->month(3)->id);
    }

    public function testDatesAndDays(): void
    {
        $world = self::world();

        self::assertSame(0, $world->day(1, 1, 1)->number);
        self::assertSame('world', $world->day(1, 1, 1)->calendar);
        self::assertSame(29, $world->day(1, 1, 30)->number);
        self::assertSame(30, $world->day(1, 2, 1)->number);
        self::assertSame(180, $world->day(2, 1, 1)->number);
        self::assertSame(540 + 60, $world->day(4, 3, 1)->number);
        self::assertSame(-180, $world->day(0, 1, 1)->number);
        self::assertSame(-181, $world->day(-1, 7, 30)->number);
        $date = $world->date(new Day('world', 540 + 60));
        self::assertSame([4, 3, 1], [$date->year, $date->month, $date->day]);
        $date = $world->date(new Day('world', -1));
        self::assertSame([0, 7, 30], [$date->year, $date->month, $date->day]);
        self::assertSame(-1, $world->dayOf(new Date(0, 7, 30))->number);
    }

    public function testEveryDayGoesToItsDateAndBack(): void
    {
        $world = self::world();
        $expected = $world->day(-20, 1, 1)->number;
        for ($year = -20; $year <= 20; ++$year) {
            for ($month = 1; $month <= 7; ++$month) {
                for ($dayOfMonth = 1; $dayOfMonth <= $world->monthLength($year, $month); ++$dayOfMonth) {
                    $day = $world->day($year, $month, $dayOfMonth);
                    self::assertSame($expected, $day->number);
                    $date = $world->date($day);
                    self::assertSame([$year, $month, $dayOfMonth], [$date->year, $date->month, $date->day]);
                    ++$expected;
                }
            }
        }
    }

    public function testAMissingDayIsRefused(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The month 3 of the year 1 has days 1 to 0; 1 is not one.');

        self::world()->day(1, 3, 1);
    }

    public function testDayZeroOfAMonthIsRefused(): void
    {
        $this->expectException(InvalidCalendar::class);

        self::world()->day(1, 1, 0);
    }

    public function testAMissingMonthIsRefused(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The calendar "world" has months 1 to 7; 8 is not one.');

        self::world()->day(1, 8, 1);
    }

    public function testMonthZeroIsRefused(): void
    {
        $this->expectException(InvalidCalendar::class);

        self::world()->month(0);
    }

    public function testAYearOutOfBoundsIsRefused(): void
    {
        $this->expectException(OutOfRange::class);
        $this->expectExceptionMessageIsOrContains('A year lies from -1000000 to 1000000; it is 1000001.');

        self::world()->yearLength(Limits::YEAR + 1);
    }

    public function testADayOutOfBoundsIsRefused(): void
    {
        $world = self::world();
        $last = $world->day(Limits::YEAR, 7, 30);

        $this->expectException(OutOfRange::class);
        $this->expectExceptionMessageIsOrContains('lies outside the years -1000000 to 1000000');

        $world->date($last->plus(1));
    }

    public function testADayBeforeTheBoundsIsRefused(): void
    {
        $world = self::world();

        $this->expectException(OutOfRange::class);

        $world->date($world->day(-Limits::YEAR, 1, 1)->plus(-1));
    }

    public function testADayOfAnotherCalendarIsRefused(): void
    {
        $this->expectException(CalendarMismatch::class);
        $this->expectExceptionMessageIsOrContains('A day of the calendar "other" is not a day of the calendar "world": convert it first.');

        self::world()->date(new Day('other', 0));
    }

    public function testMoonPhasesOfAnotherCalendarAreRefused(): void
    {
        $this->expectException(CalendarMismatch::class);

        self::world()->moonPhases(new Day('other', 0));
    }

    public function testWeekdaysSkipTheDaysOutsideTheWeek(): void
    {
        $world = self::world();

        self::assertSame(2, $world->weekday(new Day('world', 0)));
        self::assertSame('Threeday', $world->weekdayName(new Day('world', 0)));
        $weekday = static fn (int $year, int $month, int $day): int => $world->weekday($world->day($year, $month, $day)) ?? -1;
        $before = $weekday(4, 2, 30);
        self::assertNull($world->weekday($world->day(4, 3, 1)));
        self::assertNull($world->weekdayName($world->day(4, 3, 1)));
        self::assertSame(($before + 1) % 6, $world->weekday($world->day(4, 4, 1)));
        self::assertSame(($weekday(7, 7, 30) + 1) % 6, $world->weekday($world->day(8, 1, 1)));
        self::assertSame(($weekday(-1, 7, 30) + 1) % 6, $world->weekday($world->day(0, 1, 1)));
        // Year 3 has 180 days in the week, a multiple of 6: its first day has the weekday of year 2's first day.
        self::assertSame($world->weekday($world->day(2, 1, 1)), $world->weekday($world->day(3, 1, 1)));
        // Year 4 is leap: its Midfeast stands outside the week, its extra day of Harvest inside.
        self::assertSame(($weekday(4, 1, 1) + 1) % 6, $world->weekday($world->day(5, 1, 1)));
    }

    public function testMoons(): void
    {
        $world = self::world();

        self::assertSame(['pale' => Phase::New, 'red' => Phase::FirstQuarter], $world->moonPhases(new Day('world', 3)));
        self::assertSame(Phase::Full, $world->moonPhases(new Day('world', 7))['pale']);
        self::assertSame(Phase::Full, $world->moonPhases(new Day('world', 6))['red']);
    }

    public function testEras(): void
    {
        $world = self::world();
        $era = static fn (EraYear $year): array => [$year->era?->id, $year->year];

        self::assertSame(['before', 1], $era($world->yearInEra(0)));
        self::assertSame(['before', 6], $era($world->yearInEra(-5)));
        self::assertSame(['founding', 1], $era($world->yearInEra(1)));
        self::assertSame(['founding', 299], $era($world->yearInEra(299)));
        self::assertSame(['kings', 1], $era($world->yearInEra(300)));
        self::assertSame(['kings', 701], $era($world->yearInEra(1000)));
        self::assertSame(299, $world->yearFromEra('founding', 299));
        self::assertSame(304, $world->yearFromEra('kings', 5));
        self::assertSame(0, $world->yearFromEra('before', 1));
        self::assertSame(-9, $world->yearFromEra('before', 10));
        self::assertSame(1, $world->yearFromEra('founding', 1));
    }

    public function testErasWithoutABackwardEra(): void
    {
        $calendar = new Calendar('c', 'C', [new Month('m', 'M', 10)], ['D'], eras: [new Era('a', 'A', 10), new Era('b', 'B', 20)]);
        $era = static fn (EraYear $year): array => [$year->era?->id, $year->year];

        self::assertSame([null, 9], $era($calendar->yearInEra(9)));
        self::assertSame(['a', 1], $era($calendar->yearInEra(10)));
        self::assertSame(['b', 1], $era($calendar->yearInEra(20)));
        self::assertSame([null, -3], $era(new Calendar('d', 'D', [new Month('m', 'M', 10)], ['D'])->yearInEra(-3)));
    }

    public function testAYearPastItsEraIsRefused(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The era "founding" has no year 300.');

        self::world()->yearFromEra('founding', 300);
    }

    public function testYearZeroOfAnEraIsRefused(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The era "kings" has no year 0.');

        self::world()->yearFromEra('kings', 0);
    }

    public function testAnUnknownEraIsRefused(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The calendar "world" has no era "dragons".');

        self::world()->yearFromEra('dragons', 1);
    }

    public function testAYearOfEraOutOfBoundsIsRefused(): void
    {
        $this->expectException(OutOfRange::class);

        self::world()->yearInEra(-Limits::YEAR - 1);
    }

    public function testSeasons(): void
    {
        $world = self::world();
        $cold = $world->seasonOn($world->day(4, 1, 1));
        $warm = $world->seasonOn($world->day(4, 7, 30));
        $lateWarm = $world->seasonOn($world->day(5, 4, 1));

        self::assertNotNull($cold);
        self::assertSame(['cold', 'temperate', 1, 61], [$cold->season->id, $cold->climateZone, $cold->day, $cold->length]);
        self::assertNotNull($warm);
        self::assertSame(['warm', 121, 121], [$warm->season->id, $warm->day, $warm->length]);
        self::assertNotNull($lateWarm);
        self::assertSame(['warm', 1, 120], [$lateWarm->season->id, $lateWarm->day, $lateWarm->length]);
        self::assertSame(60, $world->seasonOn($world->day(1, 2, 30))?->day);
    }

    public function testACalendarWithoutSeasonsHasNone(): void
    {
        self::assertNull(new Calendar('c', 'C', [new Month('m', 'M', 10)], ['D'])->seasonOn(new Day('c', 0)));
    }

    public function testYearsBetween(): void
    {
        $world = self::world();

        self::assertSame(0, $world->yearsBetween($world->day(1, 4, 10), $world->day(2, 4, 9)));
        self::assertSame(1, $world->yearsBetween($world->day(1, 4, 10), $world->day(2, 4, 10)));
        self::assertSame(1, $world->yearsBetween($world->day(1, 4, 10), $world->day(2, 5, 1)));
        self::assertSame(0, $world->yearsBetween($world->day(1, 4, 10), $world->day(1, 7, 30)));
        self::assertSame(-1, $world->yearsBetween($world->day(2, 4, 10), $world->day(1, 4, 10)));
        self::assertSame(0, $world->yearsBetween($world->day(2, 4, 10), $world->day(1, 4, 11)));
        self::assertSame(-1, $world->yearsBetween($world->day(2, 4, 10), $world->day(1, 4, 9)));
        self::assertSame(-11, $world->yearsBetween($world->day(5, 1, 1), $world->day(-6, 1, 1)));
        // Born on the leap day of Harvest: the anniversary comes the day after the 30th in a common year.
        self::assertSame(0, $world->yearsBetween($world->day(4, 6, 31), $world->day(5, 6, 30)));
        self::assertSame(1, $world->yearsBetween($world->day(4, 6, 31), $world->day(5, 7, 1)));
    }

    public function testCalendarProperties(): void
    {
        $world = self::world();

        self::assertSame('world', $world->id);
        self::assertSame('Reckoning of the Vale', $world->name);
        self::assertCount(7, $world->months);
        self::assertCount(6, $world->weekdays);
        self::assertCount(2, $world->moons);
        self::assertCount(3, $world->eras);
        self::assertCount(2, $world->seasons);
        self::assertSame('temperate', $world->climateZone);
        self::assertSame(2, $world->firstWeekday);
    }

    /**
     * @return iterable<string, array{Closure(): Calendar, string}>
     */
    public static function invalidCalendars(): iterable
    {
        $month = new Month('m', 'M', 10);

        yield 'no month' => [static fn (): Calendar => new Calendar('c', 'C', [], ['D']), 'A calendar has 1 to 100 months.'];
        yield 'too many months' => [static fn (): Calendar => new Calendar('c', 'C', array_map(static fn (int $i): Month => new Month('m'.$i, 'M', 1), range(1, 101)), ['D']), 'A calendar has 1 to 100 months.'];
        yield 'no weekday' => [static fn (): Calendar => new Calendar('c', 'C', [$month], []), 'A week has 1 to 100 days.'];
        yield 'too many weekdays' => [static fn (): Calendar => new Calendar('c', 'C', [$month], array_fill(0, 101, 'D')), 'A week has 1 to 100 days.'];
        yield 'blank weekday' => [static fn (): Calendar => new Calendar('c', 'C', [$month], [' ']), 'A weekday name is a text of 1 to 200 characters.'];
        yield 'first weekday' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D', 'E'], 2), 'The weekday of day 0 lies from 0 to 1; it is 2.'];
        yield 'bad identifier' => [static fn (): Calendar => new Calendar('-c', 'C', [$month], ['D']), 'A calendar identifier is 1 to 64'];
        yield 'blank name' => [static fn (): Calendar => new Calendar('c', '', [$month], ['D']), 'A calendar name is a text of 1 to 200 characters.'];
        yield 'bad climate zone' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], climateZone: 'a zone'), 'A climate zone is 1 to 64'];
        yield 'two months alike' => [static fn (): Calendar => new Calendar('c', 'C', [$month, $month], ['D']), 'Two months are named "m".'];
        yield 'leap-only months' => [static fn (): Calendar => new Calendar('c', 'C', [new Month('m', 'M', 0, 1)], ['D']), 'A common year has at least one day.'];
        yield 'too many moons' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], moons: array_map(static fn (int $i): Moon => new Moon('m'.$i, 'M', 5), range(1, 17))), 'A calendar has at most 16 moons.'];
        yield 'two moons alike' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], moons: [new Moon('m', 'M', 5), new Moon('m', 'N', 6)]), 'Two moons are named "m".'];
        yield 'too many eras' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], eras: array_map(static fn (int $i): Era => new Era('e'.$i, 'E', $i), range(1, 101))), 'A calendar has at most 100 eras.'];
        yield 'two eras alike' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], eras: [new Era('e', 'E', 1), new Era('e', 'F', 2)]), 'Two eras are named "e".'];
        yield 'backward era alone' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], eras: [new Era('e', 'E', null)]), 'An era that counts backward comes first, before a dated era.'];
        yield 'backward era second' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], eras: [new Era('e', 'E', 1), new Era('f', 'F', null)]), 'An era that counts backward comes first, before a dated era.'];
        yield 'eras out of order' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], eras: [new Era('e', 'E', 5), new Era('f', 'F', 5)]), 'The eras come in the order of their first years, each after the one before.'];
        yield 'too many seasons' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], seasons: array_map(static fn (int $i): Season => new Season('s'.$i, 'S', 1, 1), range(1, 17))), 'A calendar has at most 16 seasons.'];
        yield 'two seasons alike' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], seasons: [new Season('s', 'S', 1, 1), new Season('s', 'T', 1, 2)]), 'Two seasons are named "s".'];
        yield 'season in no month' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], seasons: [new Season('s', 'S', 2, 1)]), 'The season "s" starts on a day every year has.'];
        yield 'season on day 0' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], seasons: [new Season('s', 'S', 1, 0)]), 'The season "s" starts on a day every year has.'];
        yield 'season on a leap day' => [static fn (): Calendar => new Calendar('c', 'C', [new Month('m', 'M', 10, 1)], ['D'], seasons: [new Season('s', 'S', 1, 11)]), 'The season "s" starts on a day every year has.'];
        yield 'seasons out of order' => [static fn (): Calendar => new Calendar('c', 'C', [$month], ['D'], seasons: [new Season('s', 'S', 1, 5), new Season('t', 'T', 1, 5)]), 'The seasons come in the order of the year, each after the one before.'];
    }

    /**
     * @param Closure(): Calendar $make
     */
    #[DataProvider('invalidCalendars')]
    public function testAnInconsistentCalendarIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheLimitsAreReachable(): void
    {
        $calendar = new Calendar(
            'c',
            'C',
            array_map(static fn (int $i): Month => new Month('m'.$i, 'M', 1), range(1, 100)),
            array_fill(0, 100, 'D'),
            99,
            moons: array_map(static fn (int $i): Moon => new Moon('m'.$i, 'M', 5), range(1, 16)),
            eras: [new Era('b', 'B', null), ...array_map(static fn (int $i): Era => new Era('e'.$i, 'E', $i), range(1, 99))],
            seasons: array_map(static fn (int $i): Season => new Season('s'.$i, 'S', $i, 1), range(1, 16)),
        );

        self::assertSame(100, $calendar->yearLength(1));
        self::assertSame('c', new Calendar('c', 'C', [new Month('m', 'M', 10)], ['D'], seasons: [new Season('s', 'S', 1, 10)])->id);
    }

    private function festivalYear(): Calendar
    {
        return new Calendar(
            'festivals',
            'Festival Year',
            [
                new Month('a', 'A', 8),
                new Month('first', 'First Festival', 1, 1, false),
                new Month('b', 'B', 8, 1),
                new Month('second', 'Second Festival', 2, 2, false),
            ],
            ['One', 'Two', 'Three', 'Four', 'Five'],
            2,
            new LeapRule([new LeapCycle(2, 1)]),
        );
    }

    public function testOnlyTheDaysOfTheWeekAreCountedInTheWeek(): void
    {
        $calendar = $this->festivalYear();
        $days = [];
        for ($year = -10; $year <= 12; ++$year) {
            for ($month = 1; $month <= 4; ++$month) {
                for ($dayOfMonth = 1; $dayOfMonth <= $calendar->monthLength($year, $month); ++$dayOfMonth) {
                    $days[] = [$year, $month, $dayOfMonth];
                }
            }
        }

        $anchor = array_search([1, 1, 1], $days, true);
        self::assertIsInt($anchor);

        // The days of the week before and after the first day of year 1, counted one by one.
        $inWeek = static fn (int $index): bool => $calendar->month($days[$index][1])->inWeek;
        $position = [];
        $counted = 0;
        for ($index = $anchor; $index < \count($days); ++$index) {
            $position[$index] = $inWeek($index) ? $counted++ : null;
        }

        $counted = 0;
        for ($index = $anchor - 1; $index >= 0; --$index) {
            $position[$index] = $inWeek($index) ? --$counted : null;
        }

        foreach ($days as $index => [$year, $month, $dayOfMonth]) {
            $day = $calendar->day($year, $month, $dayOfMonth);
            self::assertSame($index - $anchor, $day->number);
            $date = $calendar->date($day);
            self::assertSame([$year, $month, $dayOfMonth], [$date->year, $date->month, $date->day]);
            self::assertSame(null === $position[$index] ? null : Limits::mod(2 + $position[$index], 5), $calendar->weekday($day));
        }
    }

    public function testAnAgeInTheSameYearIsZero(): void
    {
        $world = self::world();

        self::assertSame(0, $world->yearsBetween($world->day(1, 2, 1), $world->day(1, 1, 1)));
        self::assertSame(0, $world->yearsBetween($world->day(1, 1, 1), $world->day(1, 2, 1)));
    }

    public function testEraAfterABackwardOneMustStillFollowTheEraBefore(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('The eras come in the order of their first years, each after the one before.');

        new Calendar('c', 'C', [new Month('m', 'M', 10)], ['D'], 0, new LeapRule(), [], [new Era('b', 'B', null), new Era('x', 'X', 5), new Era('y', 'Y', 3)]);
    }
}
