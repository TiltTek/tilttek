<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\SeasonalEvents\Days;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;

#[CoversClass(Days::class)]
#[CoversClass(InvalidSeason::class)]
final class DaysTest extends TestCase
{
    public function testTheNumberOfADayCountsFromTheEpoch(): void
    {
        self::assertSame(0, Days::number('1970-01-01'));
        self::assertSame(-1, Days::number('1969-12-31'));
        self::assertSame(11_017, Days::number('2000-03-01'));
        self::assertSame(19_782, Days::number('2024-02-29'));
        self::assertSame(Days::LAST_DAY, Days::number('8999-12-31'));
        self::assertSame(-719_468, Days::fromCivil(0, 3, 1));
        self::assertSame('2024-02-29', Days::date(19_782));
        self::assertSame(2_026, Days::year(Days::number('2026-12-31')));
        self::assertSame(2_027, Days::year(Days::number('2027-01-01')));
    }

    public function testEveryDayFollowsTheOneBeforeFromTheYearMinus1000ToTheYear9000(): void
    {
        $expected = Days::fromCivil(-1000, 1, 1);
        $months = 0;

        for ($year = -1000; $year <= 9000; ++$year) {
            $leap = (0 === $year % 4 && 0 !== $year % 100) || 0 === $year % 400;

            foreach ([31, $leap ? 29 : 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31] as $index => $length) {
                $first = Days::fromCivil($year, $index + 1, 1);
                $last = Days::fromCivil($year, $index + 1, $length);

                if ($expected !== $first || $first + $length - 1 !== $last) {
                    self::fail(\sprintf('The month %d of the year %d does not follow the one before it.', $index + 1, $year));
                }

                $expected = $last + 1;
                ++$months;
            }
        }

        self::assertSame(10_001 * 12, $months);
    }

    public function testADayIsReadInTheTimezoneOfTheSeason(): void
    {
        $moment = new DateTimeImmutable('2026-12-24 23:30', new DateTimeZone('UTC'));

        self::assertSame(Days::number('2026-12-24'), Days::of($moment, new DateTimeZone('UTC')));
        self::assertSame(Days::number('2026-12-25'), Days::of($moment, new DateTimeZone('Asia/Tokyo')));
        self::assertSame(Days::number('2026-12-24'), Days::of($moment, new DateTimeZone('America/Toronto')));
        self::assertSame('2026-12-25T00:00:00+09:00', Days::start(Days::number('2026-12-25'), new DateTimeZone('Asia/Tokyo'))->format(\DATE_ATOM));
        self::assertSame(3, Days::between(new DateTimeImmutable('2026-12-01 18:00', new DateTimeZone('UTC')), new DateTimeImmutable('2026-12-04 01:00', new DateTimeZone('UTC'))));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'a prefix' => ['x2026-12-01'];
        yield 'a suffix' => ['2026-12-01x'];
        yield 'a final line break' => ["2026-12-01\n"];
        yield 'a year of three digits' => ['202-12-01'];
        yield 'a year of five digits' => ['20266-12-01'];
        yield 'a year before 1000' => ['0999-12-31'];
        yield 'a year after 8999' => ['9000-01-01'];
        yield 'a month of one digit' => ['2026-1-01'];
        yield 'a day of one digit' => ['2026-12-1'];
        yield 'a day that does not exist' => ['2026-02-29'];
        yield 'a month that does not exist' => ['2026-13-01'];
        yield 'a leap day of a century' => ['1900-02-29'];
    }

    #[DataProvider('refused')]
    public function testADateNotWrittenAsExpectedIsRefused(string $date): void
    {
        $this->expectException(InvalidSeason::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a date of the years 1000 to 8999 written YYYY-MM-DD.', $date));

        Days::number($date);
    }

    public function testTheLeapDaysOfTheCenturiesThatHaveOneAreAccepted(): void
    {
        self::assertSame('2000-02-29', Days::date(Days::number('2000-02-29')));
        self::assertSame('1000-01-01', Days::date(Days::number('1000-01-01')));
        self::assertSame(1_000, Days::year(Days::number('1000-01-01')));
    }

    public function testAnExceptionKeepsItsPreviousAndHasNoCode(): void
    {
        $previous = new RuntimeException('before');
        $exception = InvalidSeason::because('reason', $previous);

        self::assertSame('reason', $exception->getMessage());
        self::assertSame(0, $exception->getCode());
        self::assertSame($previous, $exception->getPrevious());
        self::assertNull(InvalidSeason::because('reason')->getPrevious());
    }
}
