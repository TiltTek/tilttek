<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Period;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Names;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Period\PeriodKind;
use Tilttek\Leaderboards\Period\Schedule;

#[CoversClass(Period::class)]
#[CoversClass(Schedule::class)]
#[CoversClass(Names::class)]
final class ScheduleTest extends TestCase
{
    public function testAPeriod(): void
    {
        $period = new Period('2026-11', self::at('2026-11-01 00:00'), self::at('2026-12-01 00:00'));
        $ever = Period::ever();

        self::assertSame('ever', $ever->code);
        self::assertNull($ever->start);
        self::assertNull($ever->end);
        self::assertTrue($ever->holds(self::at('1970-01-01 00:00')));
        self::assertFalse($ever->isOverAt(self::at('2999-01-01 00:00')));
        self::assertFalse($period->holds(self::at('2026-10-31 23:59:59')));
        self::assertTrue($period->holds(self::at('2026-11-01 00:00')));
        self::assertTrue($period->holds(self::at('2026-11-30 23:59:59')));
        self::assertFalse($period->holds(self::at('2026-12-01 00:00')));
        self::assertFalse($period->isOverAt(self::at('2026-11-30 23:59:59')));
        self::assertTrue($period->isOverAt(self::at('2026-12-01 00:00')));
        self::assertSame('9', new Period('9')->code);
    }

    /**
     * @return iterable<string, array{string, ?string, ?string, string}>
     */
    public static function refusedPeriods(): iterable
    {
        yield 'a code with a capital' => ['Summer', null, null, '"Summer" is not a period name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit.'];
        yield 'a code starting with a dash' => ['-a', null, null, '"-a" is not a period name'];
        yield 'a code too long' => [str_repeat('a', 65), null, null, 'is not a period name'];
        yield 'a start alone' => ['a', '2026-11-01', null, 'The period "a" has both a start and an end, or neither.'];
        yield 'an end alone' => ['a', null, '2026-11-01', 'The period "a" has both a start and an end, or neither.'];
        yield 'an end at the start' => ['a', '2026-11-01', '2026-11-01', 'The period "a" ends after it starts.'];
    }

    #[DataProvider('refusedPeriods')]
    public function testAPeriodIsRefused(string $code, ?string $start, ?string $end, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Period($code, null === $start ? null : self::at($start), null === $end ? null : self::at($end));
    }

    public function testACodeOfSixtyFourIsAccepted(): void
    {
        self::assertSame(64, \strlen(new Period('a'.str_repeat('-', 63))->code));
    }

    /**
     * @return iterable<string, array{PeriodKind, string, string, string, string}>
     */
    public static function calendarPeriods(): iterable
    {
        yield 'a day in Toronto' => [PeriodKind::Day, '2026-11-08 03:00', '2026-11-07', '2026-11-07 05:00', '2026-11-08 05:00'];
        yield 'the day the clocks go back' => [PeriodKind::Day, '2026-11-01 12:00', '2026-11-01', '2026-11-01 04:00', '2026-11-02 05:00'];
        yield 'a week from a Sunday' => [PeriodKind::Week, '2026-11-08 20:00', '2026-w45', '2026-11-02 05:00', '2026-11-09 05:00'];
        yield 'a week from a Monday' => [PeriodKind::Week, '2026-11-09 06:00', '2026-w46', '2026-11-09 05:00', '2026-11-16 05:00'];
        yield 'the first ISO week of a year' => [PeriodKind::Week, '2027-01-01 12:00', '2026-w53', '2026-12-28 05:00', '2027-01-04 05:00'];
        yield 'a month' => [PeriodKind::Month, '2026-12-01 04:00', '2026-11', '2026-11-01 04:00', '2026-12-01 05:00'];
        yield 'a first quarter' => [PeriodKind::Quarter, '2026-03-31 12:00', '2026-q1', '2026-01-01 05:00', '2026-04-01 04:00'];
        yield 'a second quarter' => [PeriodKind::Quarter, '2026-04-01 12:00', '2026-q2', '2026-04-01 04:00', '2026-07-01 04:00'];
        yield 'a fourth quarter' => [PeriodKind::Quarter, '2026-12-31 12:00', '2026-q4', '2026-10-01 04:00', '2027-01-01 05:00'];
        yield 'a year' => [PeriodKind::Year, '2027-01-01 04:00', '2026', '2026-01-01 05:00', '2027-01-01 05:00'];
    }

    #[DataProvider('calendarPeriods')]
    public function testACalendarPeriod(PeriodKind $kind, string $moment, string $code, string $start, string $end): void
    {
        $schedule = new Schedule($kind, new DateTimeZone('America/Toronto'));
        $period = $schedule->at(self::at($moment));

        self::assertSame([$code, $start, $end], [$period?->code, $period?->start?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'), $period?->end?->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i')]);
        self::assertEquals($period, $schedule->named($code));
    }

    public function testTheTimezoneIsUtcByDefault(): void
    {
        self::assertSame('2026-11-08', new Schedule(PeriodKind::Day)->at(self::at('2026-11-08 03:00'))?->code);
    }

    /**
     * @return iterable<string, array{PeriodKind, string}>
     */
    public static function unknownCodes(): iterable
    {
        yield 'a day of a month too far' => [PeriodKind::Day, '2026-13-01'];
        yield 'a day zero' => [PeriodKind::Day, '2026-11-00'];
        yield 'a day without its dashes' => [PeriodKind::Day, '20261107'];
        yield 'a day with a suffix' => [PeriodKind::Day, '2026-11-07x'];
        yield 'a day with a prefix' => [PeriodKind::Day, 'x2026-11-07'];
        yield 'a week too far' => [PeriodKind::Week, '2026-w54'];
        yield 'a week zero' => [PeriodKind::Week, '2026-w00'];
        yield 'a week with a suffix' => [PeriodKind::Week, '2026-w45x'];
        yield 'a week with a prefix' => [PeriodKind::Week, 'x2026-w45'];
        yield 'a month too far' => [PeriodKind::Month, '2026-13'];
        yield 'a month with a suffix' => [PeriodKind::Month, '2026-11x'];
        yield 'a month with a prefix' => [PeriodKind::Month, 'x2026-11'];
        yield 'a fifth quarter' => [PeriodKind::Quarter, '2026-q5'];
        yield 'a quarter with a suffix' => [PeriodKind::Quarter, '2026-q4x'];
        yield 'a quarter with a prefix' => [PeriodKind::Quarter, 'x2026-q4'];
        yield 'a year of two digits' => [PeriodKind::Year, '26'];
        yield 'a year with a suffix' => [PeriodKind::Year, '2026x'];
        yield 'a year with a prefix' => [PeriodKind::Year, 'x2026'];
        yield 'a month for a year' => [PeriodKind::Year, '2026-11'];
        yield 'a day for ever' => [PeriodKind::Ever, '2026-11-07'];
    }

    #[DataProvider('unknownCodes')]
    public function testACodeNamesNoPeriod(PeriodKind $kind, string $code): void
    {
        self::assertNull(new Schedule($kind)->named($code));
    }

    public function testTheCodesNameTheirPeriodInTheTimezone(): void
    {
        $schedule = new Schedule(PeriodKind::Day, new DateTimeZone('Pacific/Kiritimati'));

        $start = $schedule->named('2026-11-07')?->start;

        self::assertInstanceOf(DateTimeImmutable::class, $start);
        self::assertSame('2026-11-07 00:00 +14:00', $start->format('Y-m-d H:i P'));
        self::assertSame('2026-11-06 10:00', $start->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i'));
        self::assertSame('2026-12-28', new Schedule(PeriodKind::Week, new DateTimeZone('Pacific/Kiritimati'))->named('2026-w53')?->start?->format('Y-m-d'));
        self::assertSame('2026-10-01', new Schedule(PeriodKind::Quarter, new DateTimeZone('Pacific/Kiritimati'))->named('2026-q4')?->start?->format('Y-m-d'));
        self::assertSame('2026-01-01', new Schedule(PeriodKind::Year, new DateTimeZone('Pacific/Kiritimati'))->named('2026')?->start?->format('Y-m-d'));
    }

    public function testEver(): void
    {
        $schedule = new Schedule(PeriodKind::Ever);

        self::assertEquals(Period::ever(), $schedule->at(self::at('2026-11-07 12:00')));
        self::assertEquals(Period::ever(), $schedule->named('ever'));
        self::assertNull($schedule->previous(self::at('2026-11-07 12:00')));
    }

    public function testSeasons(): void
    {
        $winter = new Period('winter', self::at('2026-12-21 00:00'), self::at('2027-03-20 00:00'));
        $autumn = new Period('autumn', self::at('2026-09-22 00:00'), self::at('2026-12-21 00:00'));
        $summer = new Period('summer', self::at('2026-06-21 00:00'), self::at('2026-09-01 00:00'));
        $schedule = new Schedule(PeriodKind::Season, new DateTimeZone('UTC'), $winter, $autumn, $summer);

        self::assertSame(['summer', 'autumn', 'winter'], array_keys($schedule->seasons));
        self::assertSame($autumn, $schedule->at(self::at('2026-12-20 23:59')));
        self::assertSame($winter, $schedule->at(self::at('2026-12-21 00:00')));
        self::assertNull($schedule->at(self::at('2026-09-10 00:00')));
        self::assertSame($autumn, $schedule->named('autumn'));
        self::assertNull($schedule->named('spring'));
        self::assertNull($schedule->previous(self::at('2026-08-31 23:59')));
        self::assertSame($summer, $schedule->previous(self::at('2026-09-01 00:00')));
        self::assertSame($summer, $schedule->previous(self::at('2026-12-20 23:59')));
        self::assertSame($autumn, $schedule->previous(self::at('2026-12-21 00:00')));
        self::assertSame($winter, $schedule->previous(self::at('2030-01-01 00:00')));
    }

    /**
     * @return iterable<string, array{PeriodKind, string, string}>
     */
    public static function previousPeriods(): iterable
    {
        yield 'the day before' => [PeriodKind::Day, '2026-11-07 05:00', '2026-11-06'];
        yield 'the day before, at midnight' => [PeriodKind::Day, '2026-11-07 04:59', '2026-11-05'];
        yield 'the week before' => [PeriodKind::Week, '2026-11-09 05:00', '2026-w45'];
        yield 'the month before' => [PeriodKind::Month, '2026-11-01 04:00', '2026-10'];
        yield 'the quarter before' => [PeriodKind::Quarter, '2027-01-15 12:00', '2026-q4'];
        yield 'the year before' => [PeriodKind::Year, '2027-01-01 05:00', '2026'];
    }

    #[DataProvider('previousPeriods')]
    public function testThePreviousPeriod(PeriodKind $kind, string $moment, string $code): void
    {
        self::assertSame($code, new Schedule($kind, new DateTimeZone('America/Toronto'))->previous(self::at($moment))?->code);
    }

    /**
     * @return iterable<string, array{PeriodKind, list<Period>, string}>
     */
    public static function refusedSchedules(): iterable
    {
        $summer = new Period('summer', self::at('2026-06-21 00:00'), self::at('2026-09-22 00:00'));

        yield 'seasons without a season' => [PeriodKind::Season, [], 'A schedule by seasons names at least one season, and only a schedule by seasons names any.'];
        yield 'a season on a calendar schedule' => [PeriodKind::Month, [$summer], 'A schedule by seasons names at least one season, and only a schedule by seasons names any.'];
        yield 'a season without dates' => [PeriodKind::Season, [new Period('always')], 'The season "always" has a start and an end.'];
        yield 'two seasons by one code' => [PeriodKind::Season, [$summer, new Period('summer', self::at('2027-06-21 00:00'), self::at('2027-09-22 00:00'))], 'Two seasons are coded "summer".'];
        yield 'two seasons that overlap' => [PeriodKind::Season, [new Period('late', self::at('2026-09-21 23:59'), self::at('2026-10-22 00:00')), $summer], 'The seasons "summer" and "late" overlap.'];
    }

    /**
     * @param list<Period> $seasons
     */
    #[DataProvider('refusedSchedules')]
    public function testAScheduleIsRefused(PeriodKind $kind, array $seasons, string $message): void
    {
        $this->expectException(InvalidLeaderboard::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Schedule($kind, new DateTimeZone('UTC'), ...$seasons);
    }

    public function testSeasonsMayFollowEachOther(): void
    {
        $schedule = new Schedule(PeriodKind::Season, new DateTimeZone('UTC'), new Period('a', self::at('2026-01-01 00:00'), self::at('2026-02-01 00:00')), new Period('b', self::at('2026-02-01 00:00'), self::at('2026-03-01 00:00')));

        self::assertSame(['a', 'b'], array_keys($schedule->seasons));
    }

    private static function at(string $moment): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }
}
