<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\Day;
use Tilttek\MiniGames\Exception\InvalidMiniGame;

#[CoversClass(Day::class)]
#[CoversClass(InvalidMiniGame::class)]
final class DayTest extends TestCase
{
    public function testADayReadFromItsText(): void
    {
        self::assertSame('2026-11-24', Day::fromString('2026-11-24')->value);
        self::assertSame('2000-01-01', Day::fromString('2000-01-01')->value);
        self::assertSame('9999-12-31', Day::fromString('9999-12-31')->value);
        self::assertSame('2028-02-29', Day::fromString('2028-02-29')->value);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'a day that does not exist' => ['2026-02-30'];
        yield 'a month that does not exist' => ['2026-13-01'];
        yield 'not a leap year' => ['2027-02-29'];
        yield 'before 2000' => ['1999-12-31'];
        yield 'a year of five digits' => ['10000-01-01'];
        yield 'a negative year' => ['-001-01-01'];
        yield 'a short month' => ['2026-1-01'];
        yield 'a leading space' => [' 2026-01-01'];
        yield 'a trailing line break' => ["2026-01-01\n"];
        yield 'a time' => ['2026-01-01 10:00'];
        yield 'empty' => [''];
    }

    #[DataProvider('refused')]
    public function testARefusedDay(string $day): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('"%s" is not a day written YYYY-MM-DD from 2000-01-01.', $day));

        Day::fromString($day);
    }

    public function testTheDayOfAMomentInATimezone(): void
    {
        $moment = new DateTimeImmutable('2026-11-24T03:00:00Z');

        self::assertSame('2026-11-23', Day::of($moment, new DateTimeZone('America/Toronto'))->value);
        self::assertSame('2026-11-24', Day::of($moment, new DateTimeZone('UTC'))->value);
        self::assertSame('2026-11-24', Day::of($moment, new DateTimeZone('Asia/Tokyo'))->value);
    }

    public function testTheNumberOfADayFromTheFirst(): void
    {
        $first = Day::fromString('2026-01-01');

        self::assertSame(1, $first->numberFrom($first));
        self::assertSame(2, Day::fromString('2026-01-02')->numberFrom($first));
        self::assertSame(68, Day::fromString('2026-03-09')->numberFrom($first));
        self::assertSame(367, Day::fromString('2029-01-01')->numberFrom(Day::fromString('2028-01-01')));
        self::assertSame(2_921_940, Day::fromString('9999-12-31')->numberFrom(Day::fromString('2000-01-01')));
    }

    public function testADayBeforeTheFirstHasNoNumber(): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('The day 2025-12-31 comes before the first day 2026-01-01.');

        Day::fromString('2025-12-31')->numberFrom(Day::fromString('2026-01-01'));
    }

    public function testDaysCompareAsTheCalendarDoes(): void
    {
        $day = Day::fromString('2026-11-24');

        self::assertTrue($day->isBefore(Day::fromString('2026-11-25')));
        self::assertTrue($day->isBefore(Day::fromString('2027-01-01')));
        self::assertFalse($day->isBefore(Day::fromString('2026-11-24')));
        self::assertFalse($day->isBefore(Day::fromString('2026-11-23')));
        self::assertTrue($day->equals(Day::fromString('2026-11-24')));
        self::assertFalse($day->equals(Day::fromString('2026-11-25')));
    }
}
