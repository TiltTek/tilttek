<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Exception\InvalidGoal;

#[CoversClass(Day::class)]
#[CoversClass(InvalidGoal::class)]
final class DayTest extends TestCase
{
    public function testADay(): void
    {
        $day = Day::fromString('2026-11-11');

        self::assertSame('2026-11-11', $day->toString());
        self::assertSame(20_768, $day->number);
        self::assertSame(0, Day::fromString('1970-01-01')->number);
        self::assertSame('2026-11-12', $day->plus(1)->toString());
        self::assertSame('2026-12-01', $day->plus(20)->toString());
        self::assertSame('2026-10-31', $day->plus(-11)->toString());
        self::assertSame('2026-11-11', Day::fromNumber(20_768)->toString());
        self::assertSame(3, $day->daysUntil(Day::fromString('2026-11-14')));
        self::assertSame(-1, $day->daysUntil(Day::fromString('2026-11-10')));
    }

    public function testComparingDays(): void
    {
        $day = Day::fromString('2026-11-11');
        $same = Day::fromString('2026-11-11');
        $next = $day->plus(1);

        self::assertTrue($day->equals($same));
        self::assertFalse($day->equals($next));
        self::assertTrue($day->isBefore($next));
        self::assertFalse($day->isBefore($same));
        self::assertFalse($next->isBefore($day));
        self::assertTrue($next->isAfter($day));
        self::assertFalse($day->isAfter($same));
        self::assertFalse($day->isAfter($next));
    }

    public function testTheDayOfTheWeek(): void
    {
        self::assertSame(4, Day::fromString('1970-01-01')->weekday());
        self::assertSame(3, Day::fromString('2026-11-11')->weekday());
        self::assertSame(7, Day::fromString('2026-11-15')->weekday());
        self::assertSame(1, Day::fromString('2026-11-16')->weekday());
        self::assertSame(3, Day::fromString('1969-12-31')->weekday());
        self::assertSame(7, Day::fromString('1969-12-28')->weekday());
        self::assertSame(1, Day::fromString('1969-12-29')->weekday());
    }

    public function testTheDayAMomentFallsOnInATimezone(): void
    {
        $moment = new DateTimeImmutable('2026-11-12 03:30:00 UTC');

        self::assertSame('2026-11-12', Day::of($moment, new DateTimeZone('UTC'))->toString());
        self::assertSame('2026-11-11', Day::of($moment, new DateTimeZone('America/Montreal'))->toString());
        self::assertSame('2026-11-12', Day::of($moment, new DateTimeZone('Asia/Tokyo'))->toString());
    }

    #[DataProvider('refusals')]
    public function testWhatADayRefuses(string $date): void
    {
        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a date written YYYY-MM-DD.', $date));

        Day::fromString($date);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refusals(): iterable
    {
        yield 'a date that does not exist' => ['2026-02-29'];
        yield 'no leading zero' => ['2026-1-01'];
        yield 'a time' => ['2026-11-11 10:00'];
        yield 'nothing' => [''];
    }
}
