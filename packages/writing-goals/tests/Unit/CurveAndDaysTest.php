<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\WritingGoals\Curve;
use Tilttek\WritingGoals\CurveShape;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Exception\InvalidGoal;
use Tilttek\WritingGoals\WritingDays;

#[CoversClass(Curve::class)]
#[CoversClass(CurveShape::class)]
#[CoversClass(WritingDays::class)]
final class CurveAndDaysTest extends TestCase
{
    public function testTheWeightsOfEachShape(): void
    {
        self::assertSame([1, 1, 1, 1], $this->weights(Curve::linear(), 4));
        self::assertSame([7, 6, 5, 4], $this->weights(Curve::frontLoaded(), 4));
        self::assertSame([4, 5, 6, 7], $this->weights(Curve::backLoaded(), 4));
        self::assertSame([1], $this->weights(Curve::frontLoaded(), 1));
        self::assertSame([1], $this->weights(Curve::backLoaded(), 1));
        self::assertSame([1, 1, 0, 0, 1, 1], $this->weights(Curve::custom([1, 0, 1]), 6));
        self::assertSame([1, 0, 1], $this->weights(Curve::custom([1, 0, 1]), 3));
        self::assertSame([1, 1, 0, 1], $this->weights(Curve::custom([1, 0, 1]), 4));
        self::assertSame([1, 3], $this->weights(Curve::custom([1, 2, 3, 4]), 2));
        self::assertSame(CurveShape::Custom, Curve::custom([5])->shape);
        self::assertSame([5, 0], Curve::custom([5, 0])->points);
        self::assertSame([], Curve::linear()->points);
        self::assertCount(366, Curve::custom(array_fill(0, 366, 1000))->points);
        self::assertSame([CurveShape::Linear, []], [Curve::of(CurveShape::Linear, [3])->shape, Curve::of(CurveShape::Linear, [3])->points]);
        self::assertSame(CurveShape::FrontLoaded, Curve::of(CurveShape::FrontLoaded)->shape);
        self::assertSame(CurveShape::BackLoaded, Curve::of(CurveShape::BackLoaded)->shape);
        self::assertSame([CurveShape::Custom, [2, 0]], [Curve::of(CurveShape::Custom, [2, 0])->shape, Curve::of(CurveShape::Custom, [2, 0])->points]);
    }

    /**
     * @param list<int> $points
     */
    #[DataProvider('refusedCurves')]
    public function testWhatACustomCurveRefuses(array $points, string $message): void
    {
        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains($message);

        Curve::custom($points);
    }

    /**
     * @return iterable<string, array{list<int>, string}>
     */
    public static function refusedCurves(): iterable
    {
        yield 'no weight' => [[], 'A custom curve has 1 to 366 weights; 0 were given.'];
        yield 'too many weights' => [array_fill(0, 367, 1), 'A custom curve has 1 to 366 weights; 367 were given.'];
        yield 'a negative weight' => [[1, -1], 'A weight of a custom curve is 0 to 1000; -1 is not one.'];
        yield 'a weight too heavy' => [[1001], 'A weight of a custom curve is 0 to 1000; 1001 is not one.'];
        yield 'only zeros' => [[0, 0], 'A custom curve has at least one weight above 0.'];
    }

    public function testTheWritingDays(): void
    {
        $weekdays = WritingDays::on(5, 1, 3, 1);
        $wednesday = Day::fromString('2026-11-11');

        self::assertSame([1, 2, 3, 4, 5, 6, 7], WritingDays::everyDay()->weekdays);
        self::assertSame([1, 3, 5], $weekdays->weekdays);
        self::assertTrue($weekdays->includes($wednesday));
        self::assertFalse($weekdays->includes($wednesday->plus(1)));
        self::assertSame([7], WritingDays::on(7)->weekdays);

        $holidays = $weekdays->except($wednesday->plus(7), $wednesday, $wednesday);

        self::assertFalse($holidays->includes($wednesday));
        self::assertFalse($holidays->includes($wednesday->plus(7)));
        self::assertTrue($holidays->includes($wednesday->plus(14)));
        self::assertSame(['2026-11-11', '2026-11-18'], $holidays->excluded());
        self::assertSame([], $weekdays->excluded());
        self::assertSame(['2026-11-11', '2026-11-18', '2026-11-25'], $holidays->except($wednesday->plus(14))->excluded());
    }

    public function testAnAuthorSetsAsideUpTo366Dates(): void
    {
        $days = array_map(static fn (int $offset): Day => Day::fromString('2026-01-01')->plus($offset), range(0, 365));
        $writingDays = WritingDays::everyDay()->except(...$days);

        self::assertCount(366, $writingDays->excluded());
        self::assertCount(366, $writingDays->except($days[0])->excluded());

        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains('An author sets aside 366 dates at most.');
        $writingDays->except(Day::fromString('2025-01-01'));
    }

    #[DataProvider('refusedWeekdays')]
    public function testWhatTheWritingDaysRefuse(string $message, int ...$weekdays): void
    {
        $this->expectException(InvalidGoal::class);
        $this->expectExceptionMessageIsOrContains($message);

        WritingDays::on(...$weekdays);
    }

    /**
     * @return iterable<string, list<int|string>>
     */
    public static function refusedWeekdays(): iterable
    {
        yield 'none' => ['An author writes on one day of the week at least.'];
        yield 'zero' => ['A day of the week is 1, Monday, to 7, Sunday; 0 is not one.', 1, 0];
        yield 'eight' => ['A day of the week is 1, Monday, to 7, Sunday; 8 is not one.', 8];
    }

    /**
     * @return list<int>
     */
    private function weights(Curve $curve, int $count): array
    {
        return array_map(static fn (int $position): int => $curve->weight($position, $count), range(0, $count - 1));
    }
}
