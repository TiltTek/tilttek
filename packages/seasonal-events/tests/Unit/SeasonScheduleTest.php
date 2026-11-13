<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;
use Tilttek\SeasonalEvents\Days;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;
use Tilttek\SeasonalEvents\Period;
use Tilttek\SeasonalEvents\SeasonSchedule;

#[CoversClass(SeasonSchedule::class)]
#[CoversClass(Period::class)]
#[CoversClass(Days::class)]
#[CoversClass(InvalidSeason::class)]
final class SeasonScheduleTest extends TestCase
{
    private const string ZONE = 'America/Toronto';

    public function testASeasonThatComesOnceHoldsItsDaysFromMidnightToMidnight(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2027-03-20', '2027-04-03');

        self::assertSame(15, $schedule->days);
        self::assertFalse($schedule->isYearly());
        self::assertNull($schedule->periodAt($this->at('2027-03-19 23:59:59')));

        $period = $schedule->periodAt($this->at('2027-03-20 00:00'));
        self::assertInstanceOf(Period::class, $period);
        self::assertSame('2027-03-20', $period->code);
        self::assertSame('2027-03-20T00:00:00-04:00', $period->from->format(\DATE_ATOM));
        self::assertSame('2027-04-04T00:00:00-04:00', $period->until->format(\DATE_ATOM));
        self::assertSame(15, $period->days());
        self::assertInstanceOf(Period::class, $schedule->periodAt($this->at('2027-04-03 23:59:59')));
        self::assertNull($schedule->periodAt($this->at('2027-04-04 00:00')));
    }

    public function testASeasonThatComesOnceComesNextOnlyBeforeItStarts(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2027-03-20', '2027-03-20');

        self::assertSame('2027-03-20', $schedule->nextPeriod($this->at('2027-03-19 23:59:59'))?->code);
        self::assertNull($schedule->nextPeriod($this->at('2027-03-20 00:00')));
        self::assertNull($schedule->nextPeriod($this->at('2027-05-01 00:00')));
        self::assertSame(['2027-03-20'], array_map(static fn (Period $period): string => $period->code, $schedule->periods('2027-03-20', '2027-03-20')));
        self::assertSame([], $schedule->periods('2027-03-21', '2027-12-31'));
        self::assertSame([], $schedule->periods('2027-01-01', '2027-03-19'));
    }

    public function testAYearlySeasonCrossesTheNewYearAndComesBack(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2026-12-15', 23);

        self::assertTrue($schedule->isYearly());
        self::assertNull($schedule->periodAt($this->at('2026-12-14 23:59')));
        self::assertSame('2026-12-15', $schedule->periodAt($this->at('2026-12-15 00:00'))?->code);
        self::assertSame('2026-12-15', $schedule->periodAt($this->at('2027-01-06 23:59'))?->code);
        self::assertNull($schedule->periodAt($this->at('2027-01-07 00:00')));
        self::assertSame('2027-12-15', $schedule->periodAt($this->at('2027-12-20 12:00'))?->code);
        self::assertSame('2027-12-15', $schedule->nextPeriod($this->at('2027-01-01 00:00'))?->code);
        self::assertSame('2026-12-15', $schedule->nextPeriod($this->at('2020-01-01 00:00'))?->code);
        self::assertSame(
            ['2026-12-15', '2027-12-15', '2028-12-15'],
            array_map(static fn (Period $period): string => $period->code, $schedule->periods('2026-01-01', '2028-12-31')),
        );
        self::assertCount(2, $schedule->periods('2026-01-01', '2030-12-31', 2));
        self::assertSame([], $schedule->periods('2026-01-01', '2030-12-31', 0));
        self::assertSame([], $schedule->periods('2027-01-01', '2026-01-01'));
    }

    public function testAYearlyRuleMovesTheSeasonWithItsWeekday(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2026-11-26', 4, 'FREQ=YEARLY;BYMONTH=11;BYDAY=4TH');

        self::assertSame('2026-11-26', $schedule->periodAt($this->at('2026-11-29 22:00'))?->code);
        self::assertNull($schedule->periodAt($this->at('2026-11-30 00:00')));
        self::assertSame('2027-11-25', $schedule->nextPeriod($this->at('2026-12-01 00:00'))?->code);
        self::assertSame('FREQ=YEARLY;BYDAY=4TH;BYMONTH=11', $schedule->toArray()['rule']);
    }

    public function testASeasonWithAnEndingRuleNeverComesAgain(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2026-07-01', 10, 'FREQ=YEARLY;COUNT=2');

        self::assertSame('2027-07-01', $schedule->nextPeriod($this->at('2026-08-01 00:00'))?->code);
        self::assertNull($schedule->nextPeriod($this->at('2027-08-01 00:00')));
        self::assertNull($schedule->periodAt($this->at('2028-07-02 00:00')));
    }

    public function testTheDaysOfASeasonFollowTheWallClockAcrossAChangeOfTime(): void
    {
        $schedule = SeasonSchedule::once(self::ZONE, '2026-11-01', '2026-11-01');
        $period = $schedule->periodAt($this->at('2026-11-01 23:30'));

        self::assertInstanceOf(Period::class, $period);
        self::assertSame(25 * 3600, $period->until->getTimestamp() - $period->from->getTimestamp());
        self::assertSame(1, $period->days());
        self::assertSame('2026-11-02T00:00:00-05:00', $period->until->format(\DATE_ATOM));
    }

    public function testAMomentIsReadInTheTimezoneOfTheSeason(): void
    {
        $schedule = SeasonSchedule::once('Asia/Tokyo', '2026-12-25', '2026-12-25');

        self::assertInstanceOf(Period::class, $schedule->periodAt(new DateTimeImmutable('2026-12-24 15:00', new DateTimeZone('UTC'))));
        self::assertNull($schedule->periodAt(new DateTimeImmutable('2026-12-24 14:59:59', new DateTimeZone('UTC'))));
    }

    public function testTheLaterTimeOfASeasonHoldsAMomentTwoTimesShare(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2026-03-01', 183, 'FREQ=YEARLY;BYMONTH=3,6');

        self::assertSame('2026-06-01', $schedule->periodAt($this->at('2026-06-10 00:00'))?->code);
        self::assertSame('2026-03-01', $schedule->periodAt($this->at('2026-05-31 00:00'))?->code);
    }

    public function testASeasonIsWrittenAsAnArray(): void
    {
        self::assertSame(
            ['timezone' => self::ZONE, 'first_date' => '2026-12-15', 'days' => 23, 'rule' => 'FREQ=YEARLY'],
            SeasonSchedule::yearly(self::ZONE, '2026-12-15', 23)->toArray(),
        );
        self::assertSame(
            ['timezone' => self::ZONE, 'first_date' => '2026-12-15', 'days' => 1, 'rule' => null],
            SeasonSchedule::once(self::ZONE, '2026-12-15', '2026-12-15')->toArray(),
        );
        self::assertSame(
            ['code' => '2026-12-15', 'from' => '2026-12-15T00:00:00-05:00', 'until' => '2026-12-16T00:00:00-05:00'],
            SeasonSchedule::once(self::ZONE, '2026-12-15', '2026-12-15')->periodAt($this->at('2026-12-15 08:00'))?->toArray(),
        );
    }

    public function testAPeriodTellsWhetherItHasStarted(): void
    {
        $period = new Period('2026-12-15', $this->at('2026-12-15 00:00'), $this->at('2026-12-16 00:00'));

        self::assertFalse($period->hasStarted($this->at('2026-12-14 23:59:59')));
        self::assertTrue($period->hasStarted($this->at('2026-12-15 00:00')));
        self::assertTrue($period->hasStarted($this->at('2026-12-17 00:00')));
        self::assertFalse($period->contains($this->at('2026-12-16 00:00')));
    }

    /**
     * @return iterable<string, array{callable(): SeasonSchedule, string}>
     */
    public static function refused(): iterable
    {
        yield 'a timezone' => [static fn (): SeasonSchedule => SeasonSchedule::once('Mars/Olympus', '2026-12-01', '2026-12-02'), '"Mars/Olympus" is not a timezone.'];
        yield 'a date' => [static fn (): SeasonSchedule => SeasonSchedule::once(self::ZONE, '2026-02-30', '2026-03-02'), '"2026-02-30" is not a date of the years 1000 to 8999 written YYYY-MM-DD.'];
        yield 'a date written otherwise' => [static fn (): SeasonSchedule => SeasonSchedule::yearly(self::ZONE, '1/12/2026', 3), '"1/12/2026" is not a date of the years 1000 to 8999 written YYYY-MM-DD.'];
        yield 'an end before the start' => [static fn (): SeasonSchedule => SeasonSchedule::once(self::ZONE, '2026-12-02', '2026-12-01'), 'A season lasts 1 to 183 days; 0 is not one.'];
        yield 'a season too long' => [static fn (): SeasonSchedule => SeasonSchedule::once(self::ZONE, '2026-01-01', '2026-07-03'), 'A season lasts 1 to 183 days; 184 is not one.'];
        yield 'no day' => [static fn (): SeasonSchedule => SeasonSchedule::yearly(self::ZONE, '2026-01-01', 0), 'A season lasts 1 to 183 days; 0 is not one.'];
        yield 'a monthly rule' => [static fn (): SeasonSchedule => SeasonSchedule::yearly(self::ZONE, '2026-01-01', 3, 'FREQ=MONTHLY'), 'A season comes back each year: its rule is yearly; "FREQ=MONTHLY" is not.'];
        yield 'an unreadable rule' => [static fn (): SeasonSchedule => SeasonSchedule::yearly(self::ZONE, '2026-01-01', 3, 'FREQ=YEARLY;BYHOUR=3'), 'The rule of a season is not read: The part BYHOUR of a recurrence rule is not read.'];
    }

    /**
     * @param callable(): SeasonSchedule $schedule
     */
    #[DataProvider('refused')]
    public function testAScheduleThatDoesNotHoldTogetherIsRefused(callable $schedule, string $message): void
    {
        $this->expectException(InvalidSeason::class);
        $this->expectExceptionMessageIsOrContains($message);

        $schedule();
    }

    public function testTheLongestSeasonIsAccepted(): void
    {
        self::assertSame(183, SeasonSchedule::once(self::ZONE, '2026-01-01', '2026-07-02')->days);
        self::assertSame(183, SeasonSchedule::yearly(self::ZONE, '2026-01-01', 183)->days);
        self::assertSame(1, SeasonSchedule::yearly(self::ZONE, '2026-01-01', 1)->days);
    }

    public function testAPlainYearlyRuleTakesTheFastPathAndOneThatSelectsOrEndsDoesNot(): void
    {
        $every = static fn (string $rule): mixed => new ReflectionProperty(SeasonSchedule::class, 'everyYears')->getValue(SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, $rule));

        self::assertSame(1, $every('FREQ=YEARLY'));
        self::assertSame(3, $every('FREQ=YEARLY;INTERVAL=3'));
        self::assertNull($every('FREQ=YEARLY;COUNT=3'));
        self::assertNull($every('FREQ=YEARLY;UNTIL=20300101T000000Z'));
        self::assertNull($every('FREQ=YEARLY;BYMONTH=12'));
        self::assertNull($every('FREQ=YEARLY;BYMONTHDAY=15'));
        self::assertNull($every('FREQ=YEARLY;BYDAY=MO'));
        self::assertNull($every('FREQ=YEARLY;BYDAY=MO;BYSETPOS=1;BYMONTH=12'));
        self::assertNull(new ReflectionProperty(SeasonSchedule::class, 'everyYears')->getValue(SeasonSchedule::once(self::ZONE, '2026-12-15', '2026-12-16')));
    }

    public function testASeasonThatSelectsOrEndsKeepsToItsRule(): void
    {
        $codes = static fn (SeasonSchedule $schedule): array => array_map(static fn (Period $period): string => $period->code, $schedule->periods('2026-01-01', '2036-12-31'));

        self::assertSame(['2026-12-15', '2027-03-15', '2028-03-15'], \array_slice($codes(SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;BYMONTH=3')), 0, 3));
        self::assertSame(['2026-12-15', '2027-12-15', '2028-12-15'], $codes(SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;COUNT=3')));
        self::assertSame(['2026-12-15', '2027-12-15', '2028-12-15', '2029-12-15'], $codes(SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;UNTIL=20300101T000000Z')));
        self::assertSame(['2026-12-15', '2027-01-15'], \array_slice($codes(SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;BYMONTHDAY=15')), 0, 2));
        self::assertSame(['2026-12-15', '2026-12-21'], \array_slice($codes(SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;BYMONTH=12;BYDAY=MO')), 0, 2));
    }

    public function testAnIntervalSkipsTheYearsBetween(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;INTERVAL=3');
        $codes = array_map(static fn (Period $period): string => $period->code, $schedule->periods('2026-01-01', '2036-12-31'));

        self::assertSame(['2026-12-15', '2029-12-15', '2032-12-15', '2035-12-15'], $codes);
        self::assertSame('2029-12-15', $schedule->nextPeriod($this->at('2027-01-01 00:00'))?->code);
        self::assertSame('2031-12-15', SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;INTERVAL=5')->nextPeriod($this->at('2027-01-01 00:00'))?->code);
        self::assertSame('2426-12-15', SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;INTERVAL=400')->nextPeriod($this->at('2027-01-01 00:00'))?->code);
    }

    public function testALeapDaySeasonComesBackOnlyInLeapYears(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2024-02-29', 3);

        self::assertSame(
            ['2024-02-29', '2028-02-29', '2032-02-29', '2036-02-29', '2040-02-29'],
            array_map(static fn (Period $period): string => $period->code, $schedule->periods('2024-01-01', '2040-12-31')),
        );
        self::assertSame('2028-02-29', $schedule->nextPeriod($this->at('2025-01-01 00:00'))?->code);
        self::assertSame('2028-02-29', $schedule->periodAt($this->at('2028-03-02 12:00'))?->code);
    }

    public function testTheNextPeriodIsNeverTheOneThatStartsAtTheMomentGiven(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3);

        self::assertSame('2027-12-15', $schedule->nextPeriod($this->at('2026-12-15 00:00'))?->code);
        self::assertSame('2026-12-15', $schedule->nextPeriod($this->at('2026-12-14 23:59:59'))?->code);
    }

    public function testASingleDayRangeFindsThePeriodThatStartsOnIt(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3);

        self::assertSame(['2027-12-15'], array_map(static fn (Period $period): string => $period->code, $schedule->periods('2027-12-15', '2027-12-15')));
        self::assertSame([], $schedule->periods('2027-12-14', '2027-12-14'));
        self::assertSame([], $schedule->periods('2027-06-01', '2027-06-30'));
        self::assertSame([], $schedule->periods('2028-01-01', '2028-02-01'));
        self::assertSame([], $schedule->periods('2028-12-31', '2026-01-01'));
        self::assertSame([], SeasonSchedule::yearly(self::ZONE, '2026-12-15', 3, 'FREQ=YEARLY;BYMONTH=12')->periods('2030-01-01', '2026-01-01'));
    }

    public function testTheLimitOfThePeriodsIsAHundredByDefault(): void
    {
        $schedule = SeasonSchedule::yearly(self::ZONE, '1000-12-15', 3);

        self::assertCount(100, $schedule->periods('1000-01-01', '1120-12-31'));
        self::assertCount(1, $schedule->periods('1000-01-01', '1120-12-31', 1));
        self::assertSame([], $schedule->periods('1000-01-01', '1120-12-31', -1));
        self::assertSame(['1000-12-15', '1001-12-15'], array_map(static fn (Period $period): string => $period->code, $schedule->periods('1000-01-01', '1120-12-31', 2)));
    }

    public function testADateRefusedBeforeARuleIsTheFirstReasonGiven(): void
    {
        $this->expectException(InvalidSeason::class);
        $this->expectExceptionMessageIsOrContains('"garbage" is not a date of the years 1000 to 8999 written YYYY-MM-DD.');

        SeasonSchedule::yearly(self::ZONE, 'garbage', 3, 'NOT A RULE');
    }

    private function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment, new DateTimeZone(self::ZONE));
    }
}
