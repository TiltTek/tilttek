<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\FantasyCalendar\Date;
use Tilttek\FantasyCalendar\Day;
use Tilttek\FantasyCalendar\Era;
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

#[CoversClass(Day::class)]
#[CoversClass(Date::class)]
#[CoversClass(Month::class)]
#[CoversClass(LeapCycle::class)]
#[CoversClass(LeapRule::class)]
#[CoversClass(Moon::class)]
#[CoversClass(Era::class)]
#[CoversClass(Season::class)]
#[CoversClass(Limits::class)]
final class ValuesTest extends TestCase
{
    public function testDaysAreComparedByTheirMethods(): void
    {
        $day = new Day('c', 10);

        self::assertSame(-1, $day->compare(new Day('c', 11)));
        self::assertSame(1, $day->compare(new Day('c', 9)));
        self::assertSame(0, $day->compare(new Day('c', 10)));
        self::assertTrue($day->isBefore(new Day('c', 11)));
        self::assertFalse($day->isBefore(new Day('c', 10)));
        self::assertTrue($day->isAfter(new Day('c', 9)));
        self::assertFalse($day->isAfter(new Day('c', 10)));
        self::assertTrue($day->isSameDay(new Day('c', 10)));
        self::assertFalse($day->isSameDay(new Day('c', 11)));
        self::assertSame(13, $day->plus(3)->number);
        self::assertSame('c', $day->plus(3)->calendar);
        self::assertSame(-4, $day->daysSince(new Day('c', 14)));
    }

    public function testDaysOfTwoCalendarsAreNotCompared(): void
    {
        $this->expectException(CalendarMismatch::class);

        new Day('a', 1)->isBefore(new Day('b', 2));
    }

    public function testDaysOfTwoCalendarsAreNotSubtracted(): void
    {
        $this->expectException(CalendarMismatch::class);

        new Day('a', 1)->daysSince(new Day('b', 2));
    }

    public function testDates(): void
    {
        $date = new Date(-3, 2, 10);

        self::assertSame(0, $date->compare(new Date(-3, 2, 10)));
        self::assertSame(-1, $date->compare(new Date(-2, 1, 1)));
        self::assertSame(1, $date->compare(new Date(-3, 2, 9)));
        self::assertSame(-1, $date->compare(new Date(-3, 3, 1)));
        self::assertSame(1, $date->compareWithinYear(new Date(5, 2, 9)));
        self::assertSame(0, $date->compareWithinYear(new Date(5, 2, 10)));
        self::assertSame(-1, $date->compareWithinYear(new Date(-9, 3, 1)));
    }

    public function testMonths(): void
    {
        $month = new Month('harvest', 'Harvest', 30, 1, false);

        self::assertSame(30, $month->length(false));
        self::assertSame(31, $month->length(true));
        self::assertFalse($month->inWeek);
        self::assertTrue(new Month('m', 'M', 1)->inWeek);
        self::assertSame(0, new Month('m', 'M', 1)->leapDays);
        self::assertSame(1000, new Month('m', 'M', 999, 1)->length(true));
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function invalidValues(): iterable
    {
        yield 'month without days' => [static fn (): Month => new Month('m', 'M', 0), 'The month "m" has days in a common year or in a leap year.'];
        yield 'month too long' => [static fn (): Month => new Month('m', 'M', 1001), 'The days of a month lies from 0 to 1000; it is 1001.'];
        yield 'month negative' => [static fn (): Month => new Month('m', 'M', -1), 'The days of a month lies from 0 to 1000; it is -1.'];
        yield 'leap days too many' => [static fn (): Month => new Month('m', 'M', 999, 2), 'The leap days of a month lies from 0 to 1; it is 2.'];
        yield 'month identifier' => [static fn (): Month => new Month('m m', 'M', 1), 'A month identifier is 1 to 64'];
        yield 'month name' => [static fn (): Month => new Month('m', str_repeat('n', 201), 1), 'A month name is a text of 1 to 200 characters.'];
        yield 'month name not UTF-8' => [static fn (): Month => new Month('m', "\xff", 1), 'A month name is a text of 1 to 200 characters.'];
        yield 'leap cycle of 0' => [static fn (): LeapCycle => new LeapCycle(0), 'The interval of a leap cycle lies from 1 to 10000; it is 0.'];
        yield 'leap cycle too long' => [static fn (): LeapCycle => new LeapCycle(10001), 'The interval of a leap cycle lies from 1 to 10000; it is 10001.'];
        yield 'leap offset' => [static fn (): LeapCycle => new LeapCycle(4, 4), 'The offset of a leap cycle lies from 0 to 3; it is 4.'];
        yield 'leap offset negative' => [static fn (): LeapCycle => new LeapCycle(4, -1), 'The offset of a leap cycle lies from 0 to 3; it is -1.'];
        yield 'leap cycles too many' => [static fn (): LeapRule => new LeapRule(array_fill(0, 17, new LeapCycle(2))), 'A leap rule has at most 16 cycles.'];
        yield 'leap period too long' => [static fn (): LeapRule => new LeapRule([new LeapCycle(101), new LeapCycle(103)]), 'The leap cycles repeat within 10000 years.'];
        yield 'moon too short' => [static fn (): Moon => new Moon('m', 'M', 1, 2), 'The cycle of a moon, times its denominator lies from 2 to 20000; it is 1.'];
        yield 'moon too long' => [static fn (): Moon => new Moon('m', 'M', 10001), 'The cycle of a moon, times its denominator lies from 1 to 10000; it is 10001.'];
        yield 'moon too precise' => [static fn (): Moon => new Moon('m', 'M', 10, 1_000_001), 'The denominator of a moon cycle lies from 1 to 1000000; it is 1000001.'];
        yield 'moon denominator zero' => [static fn (): Moon => new Moon('m', 'M', 10, 0), 'The denominator of a moon cycle lies from 1 to 1000000; it is 0.'];
        yield 'new moon too far' => [static fn (): Moon => new Moon('m', 'M', 10, 1, 1_000_001), 'The day of a new moon lies from -1000000 to 1000000; it is 1000001.'];
        yield 'new moon too far back' => [static fn (): Moon => new Moon('m', 'M', 10, 1, -1_000_001), 'The day of a new moon lies from -1000000 to 1000000; it is -1000001.'];
        yield 'moon identifier' => [static fn (): Moon => new Moon('', 'M', 10), 'A moon identifier is 1 to 64'];
        yield 'moon name' => [static fn (): Moon => new Moon('m', '', 10), 'A moon name is a text of 1 to 200 characters.'];
        yield 'decimal cycle' => [static fn (): Moon => Moon::ofDecimalCycle('m', 'M', '29,5'), 'The cycle of a moon is a decimal number of days with at most six decimals; "29,5" is not.'];
        yield 'decimal cycle too precise' => [static fn (): Moon => Moon::ofDecimalCycle('m', 'M', '29.1234567'), 'at most six decimals'];
        yield 'decimal cycle negative' => [static fn (): Moon => Moon::ofDecimalCycle('m', 'M', '-29.5'), 'at most six decimals'];
        yield 'decimal cycle too large' => [static fn (): Moon => Moon::ofDecimalCycle('m', 'M', '123456'), 'at most six decimals'];
        yield 'era identifier' => [static fn (): Era => new Era('é', 'E', 1), 'An era identifier is 1 to 64'];
        yield 'era name' => [static fn (): Era => new Era('e', ' ', 1), 'An era name is a text of 1 to 200 characters.'];
        yield 'era too far' => [static fn (): Era => new Era('e', 'E', 1_000_001), 'The first year of an era lies from -1000000 to 1000000; it is 1000001.'];
        yield 'era too far back' => [static fn (): Era => new Era('e', 'E', -1_000_001), 'The first year of an era lies from -1000000 to 1000000; it is -1000001.'];
        yield 'season identifier' => [static fn (): Season => new Season('', 'S', 1, 1), 'A season identifier is 1 to 64'];
        yield 'season name' => [static fn (): Season => new Season('s', '', 1, 1), 'A season name is a text of 1 to 200 characters.'];
        yield 'long identifier' => [static fn (): Season => new Season(str_repeat('s', 65), 'S', 1, 1), 'A season identifier is 1 to 64'];
    }

    /**
     * @param Closure(): mixed $make
     */
    #[DataProvider('invalidValues')]
    public function testAnInvalidValueIsRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testBoundsAreReachable(): void
    {
        self::assertSame(10000, new LeapCycle(10000, 9999)->every);
        self::assertSame(1000, new Month('m', 'M', 1000)->days);
        self::assertSame(str_repeat('s', 64), new Season(str_repeat('s', 64), str_repeat('n', 200), 1, 1)->id);
        self::assertSame(10000, new Moon('m', 'M', 10000, 1, 1_000_000)->cycleNumerator);
        self::assertSame(-1_000_000, new Moon('m', 'M', 1, 1, -1_000_000)->newMoon);
        self::assertSame(1_000_000, new Era('e', 'E', 1_000_000)->startYear);
        self::assertSame(-1_000_000, new Era('e', 'E', -1_000_000)->startYear);
        self::assertTrue(new Era('e', 'E', null)->countsBackward());
        self::assertFalse(new Era('e', 'E', 0)->countsBackward());
        self::assertCount(16, new LeapRule(array_fill(0, 16, new LeapCycle(2)))->cycles);
        self::assertSame(10000, new LeapRule([new LeapCycle(16), new LeapCycle(625)])->period);
        self::assertSame('a.b_c-9', Limits::identifier('a.b_c-9', 'An identifier'));
    }

    public function testLeapRules(): void
    {
        $rule = new LeapRule([new LeapCycle(4), new LeapCycle(100, 0, false), new LeapCycle(400)]);

        self::assertSame(400, $rule->period);
        self::assertSame(97, $rule->leapsPerPeriod);
        self::assertSame(0, $rule->leapsBefore(0));
        self::assertSame(1, $rule->leapsBefore(1));
        self::assertSame(1, $rule->leapsBefore(4));
        self::assertSame(2, $rule->leapsBefore(5));
        self::assertSame(97, $rule->leapsBefore(400));
        self::assertSame(98, $rule->leapsBefore(401));
        self::assertSame(0, $rule->leapsBefore(-3));
        self::assertSame(0, $rule->leapsBefore(-1));
        self::assertSame(-1, $rule->leapsBefore(-4));
        self::assertSame(-97, $rule->leapsBefore(-400));
        self::assertSame(1, new LeapRule()->period);
        self::assertSame(0, new LeapRule()->leapsBefore(1000));
        self::assertFalse(new LeapRule()->isLeap(4));
        self::assertTrue(new LeapCycle(3, 1)->matches(-2));
        self::assertFalse(new LeapCycle(3, 1)->matches(-1));
        self::assertTrue(new LeapCycle(3)->leap);
        self::assertSame(0, new LeapCycle(3)->offset);
        self::assertSame(12, new LeapRule([new LeapCycle(4), new LeapCycle(6)])->period);
    }

    public function testMoonsReduceTheirCycle(): void
    {
        $moon = Moon::ofDecimalCycle('m', 'M', '29.530588', 5);

        self::assertSame(7382647, $moon->cycleNumerator);
        self::assertSame(250000, $moon->cycleDenominator);
        self::assertSame('29.530588', $moon->decimalCycle());
        self::assertSame(5, $moon->newMoon);
        self::assertSame('28', Moon::ofDecimalCycle('m', 'M', '28')->decimalCycle());
        self::assertSame('12.5', Moon::ofDecimalCycle('m', 'M', '12.50')->decimalCycle());
        self::assertSame('1', new Moon('m', 'M', 20, 20)->decimalCycle());
        self::assertSame('10.000001', Moon::ofDecimalCycle('m', 'M', '10.000001')->decimalCycle());
        self::assertSame('1.000001', Moon::ofDecimalCycle('m', 'M', '1.000001')->decimalCycle());
        self::assertNull(new Moon('m', 'M', 10, 3)->decimalCycle());
        self::assertSame(1, new Moon('m', 'M', 6, 3)->cycleDenominator);
    }

    public function testPhasesFollowTheCycle(): void
    {
        $moon = new Moon('m', 'M', 8, 1, 3);
        foreach (Phase::cases() as $index => $phase) {
            self::assertSame($phase, $moon->phaseOn(3 + $index));
            self::assertSame($phase, $moon->phaseOn(3 + $index - 80));
            self::assertSame(3 + $index, $moon->nextPhase(3, $phase));
        }

        self::assertSame(11, $moon->nextPhase(4, Phase::New));
        self::assertSame(11, $moon->nextPhase(11, Phase::New));
        $quick = new Moon('q', 'Q', 2);
        self::assertSame(Phase::New, $quick->phaseOn(0));
        self::assertSame(Phase::Full, $quick->phaseOn(1));
        self::assertNull($quick->nextPhase(0, Phase::FirstQuarter));
        $slow = Moon::ofDecimalCycle('s', 'S', '29.5');
        self::assertSame(Phase::Full, $slow->phaseOn(15));
        self::assertSame(Phase::WaningGibbous, $slow->phaseOn(18));
        self::assertSame(Phase::New, $slow->phaseOn(29));
        self::assertSame(Phase::WaningCrescent, $slow->phaseOn(27));
        self::assertSame(58, $slow->nextPhase(32, Phase::New));
    }

    public function testLimits(): void
    {
        self::assertSame(2, Limits::floorDiv(7, 3));
        self::assertSame(-3, Limits::floorDiv(-7, 3));
        self::assertSame(-2, Limits::floorDiv(-6, 3));
        self::assertSame(2, Limits::mod(-1, 3));
        self::assertSame(0, Limits::mod(-3, 3));
        self::assertSame(1, Limits::mod(4, 3));
        self::assertSame(Limits::YEAR, Limits::year(Limits::YEAR));
        self::assertSame(-Limits::YEAR, Limits::year(-Limits::YEAR));
        self::assertSame(5, Limits::between(5, 5, 5, 'A value'));
    }

    public function testYearsBelowTheBoundsAreRefused(): void
    {
        $this->expectException(OutOfRange::class);
        $this->expectExceptionMessageIsOrContains('A year lies from -1000000 to 1000000; it is -1000001.');

        Limits::year(-Limits::YEAR - 1);
    }

    public function testALongIdentifierIsShortenedInTheMessage(): void
    {
        try {
            Limits::identifier(str_repeat('x', 100).' ', 'An identifier');
            self::fail('The identifier was accepted.');
        } catch (InvalidCalendar $invalidCalendar) {
            self::assertStringContainsString('"'.str_repeat('x', 79).'…"', $invalidCalendar->getMessage());
        }
    }

    public function testAMonthMayHaveADayOnlyInALeapYearOrOnlyInACommonYear(): void
    {
        $month = new Month('a', 'A', 1, 1);

        self::assertSame(2, $month->length(true));
        self::assertSame(1, $month->length(false));
    }

    public function testALabelIsMeasuredInCharacters(): void
    {
        $name = str_repeat('é', 150);

        self::assertSame($name, Limits::label($name, 'A name'));
    }

    public function testAMoonCycleWrittenBadlyIsQuotedShortened(): void
    {
        $this->expectException(InvalidCalendar::class);
        $this->expectExceptionMessageIsOrContains('"'.str_repeat('9', 39).'…" is not.');

        Moon::ofDecimalCycle('a', 'A', str_repeat('9', 60));
    }

    public function testAShortCycleIsSearchedForAFullCycleOfDays(): void
    {
        $moon = new Moon('m', 'M', 5, 2);

        self::assertSame(3, $moon->nextPhase(0, Phase::FirstQuarter));
        self::assertSame(5, $moon->nextPhase(2, Phase::New));
        self::assertSame(6, $moon->nextPhase(3, Phase::WaxingGibbous));
    }

    public function testACycleIsWrittenWithItsDecimals(): void
    {
        self::assertSame('29.530588', Moon::ofDecimalCycle('a', 'A', '29.530588')->decimalCycle());
        self::assertSame('1.05', new Moon('b', 'B', 21, 20)->decimalCycle());
        self::assertSame('1', new Moon('c', 'C', 1)->decimalCycle());
        self::assertSame('1.000001', new Moon('d', 'D', 1_000_001, 1_000_000)->decimalCycle());
    }
}
