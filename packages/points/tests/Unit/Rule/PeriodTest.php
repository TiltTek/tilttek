<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit\Rule;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Rule\Cap;
use Tilttek\Points\Rule\Period;

#[CoversClass(Period::class)]
#[CoversClass(Cap::class)]
final class PeriodTest extends TestCase
{
    /**
     * @return iterable<string, array{Period, string, string, string, string}>
     */
    public static function bounds(): iterable
    {
        yield 'day' => [Period::Day, '2026-11-07 15:30:00 UTC', 'UTC', '2026-11-07T00:00:00+00:00', '2026-11-08T00:00:00+00:00'];
        yield 'day in a timezone' => [Period::Day, '2026-11-07 03:30:00 UTC', 'America/Toronto', '2026-11-06T00:00:00-05:00', '2026-11-07T00:00:00-05:00'];
        yield 'week from Monday' => [Period::Week, '2026-11-07 15:30:00 UTC', 'UTC', '2026-11-02T00:00:00+00:00', '2026-11-09T00:00:00+00:00'];
        yield 'week on a Sunday' => [Period::Week, '2026-11-08 23:59:59 UTC', 'UTC', '2026-11-02T00:00:00+00:00', '2026-11-09T00:00:00+00:00'];
        yield 'week on a Monday' => [Period::Week, '2026-11-09 00:00:00 UTC', 'UTC', '2026-11-09T00:00:00+00:00', '2026-11-16T00:00:00+00:00'];
        yield 'week across a year' => [Period::Week, '2027-01-01 12:00:00 UTC', 'UTC', '2026-12-28T00:00:00+00:00', '2027-01-04T00:00:00+00:00'];
        yield 'week of the ISO year' => [Period::Week, '2026-12-31 12:00:00 UTC', 'UTC', '2026-12-28T00:00:00+00:00', '2027-01-04T00:00:00+00:00'];
        yield 'month' => [Period::Month, '2026-11-30 23:00:00 UTC', 'UTC', '2026-11-01T00:00:00+00:00', '2026-12-01T00:00:00+00:00'];
        yield 'month in a timezone' => [Period::Month, '2026-12-01 03:00:00 UTC', 'America/Toronto', '2026-11-01T00:00:00-04:00', '2026-12-01T00:00:00-05:00'];
        yield 'year' => [Period::Year, '2026-11-07 15:30:00 UTC', 'UTC', '2026-01-01T00:00:00+00:00', '2027-01-01T00:00:00+00:00'];
        yield 'year in a timezone' => [Period::Year, '2027-01-01 03:00:00 UTC', 'America/Toronto', '2026-01-01T00:00:00-05:00', '2027-01-01T00:00:00-05:00'];
    }

    #[DataProvider('bounds')]
    public function testTheBoundsOfAPeriod(Period $period, string $moment, string $timezone, string $start, string $end): void
    {
        [$since, $until] = $period->bounds(new DateTimeImmutable($moment), new DateTimeZone($timezone));

        self::assertSame([$start, $end], [$since?->format(\DATE_ATOM), $until?->format(\DATE_ATOM)]);
    }

    public function testEverHasNoBounds(): void
    {
        self::assertSame([null, null], Period::Ever->bounds(new DateTimeImmutable(), new DateTimeZone('UTC')));
        self::assertSame(['day', 'week', 'month', 'year', 'ever'], array_map(static fn (Period $period): string => $period->value, Period::cases()));
    }

    public function testACap(): void
    {
        self::assertSame([1, Period::Week], [new Cap(1, Period::Week)->amount, new Cap(1, Period::Week)->period]);
        self::assertSame(1_000_000_000_000_000, new Cap(1_000_000_000_000_000, Period::Day)->amount);

        foreach ([0, 1_000_000_000_000_001] as $amount) {
            try {
                new Cap($amount, Period::Day);
                self::fail('A cap out of bounds was expected to be refused.');
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('A cap is 1 to 1000000000000000 points; this one is %d.', $amount), $invalidPoints->getMessage());
            }
        }
    }
}
