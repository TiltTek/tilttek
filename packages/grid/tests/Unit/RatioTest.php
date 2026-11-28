<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Ratio;

#[CoversClass(Ratio::class)]
final class RatioTest extends TestCase
{
    public function testItKeepsLowestTermsWithAPositiveDenominator(): void
    {
        $ratio = Ratio::of(6, -4);

        self::assertSame(-3, $ratio->numerator);
        self::assertSame(2, $ratio->denominator);
        self::assertSame('-3/2', (string) $ratio);
        self::assertSame('5', (string) Ratio::of(10, 2));
        self::assertSame(1, Ratio::of(0, 7)->denominator);
        self::assertSame(0, Ratio::of(0, -7)->numerator);
        self::assertSame(1, Ratio::of(5)->denominator);
    }

    public function testItRefusesAZeroDenominator(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('A ratio has a denominator other than zero.');

        Ratio::of(1, 0);
    }

    public function testItRefusesTheSmallestIntegerAsNumerator(): void
    {
        $this->expectException(OutOfBounds::class);

        Ratio::of(\PHP_INT_MIN);
    }

    public function testItRefusesTheSmallestIntegerAsDenominator(): void
    {
        $this->expectException(OutOfBounds::class);

        Ratio::of(1, \PHP_INT_MIN);
    }

    public function testArithmeticIsExact(): void
    {
        $third = Ratio::of(1, 3);
        $half = Ratio::of(1, 2);

        self::assertSame('5/6', (string) $third->plus($half));
        self::assertSame('-1/6', (string) $third->minus($half));
        self::assertSame('1/6', (string) $third->times($half));
        self::assertSame('2/3', (string) $third->dividedBy($half));
        self::assertSame('-1/3', (string) $third->negated());
        self::assertSame('1/3', (string) $third->negated()->abs());
        self::assertSame('1/3', (string) $third->abs());
    }

    public function testDividingByZeroIsRefused(): void
    {
        $this->expectException(InvalidGrid::class);

        Ratio::of(1)->dividedBy(Ratio::of(0));
    }

    public function testComparisonAndEquality(): void
    {
        self::assertSame(-1, Ratio::of(1, 3)->compare(Ratio::of(1, 2)));
        self::assertSame(1, Ratio::of(1, 2)->compare(Ratio::of(1, 3)));
        self::assertSame(0, Ratio::of(2, 4)->compare(Ratio::of(1, 2)));
        self::assertTrue(Ratio::of(2, 4)->equals(Ratio::of(1, 2)));
        self::assertFalse(Ratio::of(1, 4)->equals(Ratio::of(1, 2)));
        self::assertFalse(Ratio::of(1, 2)->equals(Ratio::of(1, 3)));
        self::assertTrue(Ratio::of(1, 9)->isPositive());
        self::assertFalse(Ratio::of(0)->isPositive());
        self::assertFalse(Ratio::of(-1, 9)->isPositive());
    }

    public function testFloorRoundsDown(): void
    {
        self::assertSame(2, Ratio::of(7, 3)->floor());
        self::assertSame(-3, Ratio::of(-7, 3)->floor());
        self::assertSame(-2, Ratio::of(-6, 3)->floor());
    }

    /**
     * @return iterable<string, array{int, int, RoundingMode, int}>
     */
    public static function roundings(): iterable
    {
        $cases = [
            // value: 5/2, -5/2, 7/2, 7/3, -7/3, 8/3, 4
            'HalfAwayFromZero' => [RoundingMode::HalfAwayFromZero, [3, -3, 4, 2, -2, 3, 4]],
            'HalfTowardsZero' => [RoundingMode::HalfTowardsZero, [2, -2, 3, 2, -2, 3, 4]],
            'HalfEven' => [RoundingMode::HalfEven, [2, -2, 4, 2, -2, 3, 4]],
            'HalfOdd' => [RoundingMode::HalfOdd, [3, -3, 3, 2, -2, 3, 4]],
            'TowardsZero' => [RoundingMode::TowardsZero, [2, -2, 3, 2, -2, 2, 4]],
            'AwayFromZero' => [RoundingMode::AwayFromZero, [3, -3, 4, 3, -3, 3, 4]],
            'NegativeInfinity' => [RoundingMode::NegativeInfinity, [2, -3, 3, 2, -3, 2, 4]],
            'PositiveInfinity' => [RoundingMode::PositiveInfinity, [3, -2, 4, 3, -2, 3, 4]],
        ];
        $values = [[5, 2], [-5, 2], [7, 2], [7, 3], [-7, 3], [8, 3], [4, 1]];
        foreach ($cases as $name => [$mode, $expected]) {
            foreach ($values as $index => [$numerator, $denominator]) {
                yield $name.' '.$numerator.'/'.$denominator => [$numerator, $denominator, $mode, $expected[$index]];
            }
        }
    }

    #[DataProvider('roundings')]
    public function testRoundingFollowsTheMode(int $numerator, int $denominator, RoundingMode $mode, int $expected): void
    {
        self::assertSame($expected, Ratio::of($numerator, $denominator)->round($mode));
    }

    public function testRoundingDefaultsToHalfAwayFromZero(): void
    {
        self::assertSame(3, Ratio::of(5, 2)->round());
        self::assertSame(-3, Ratio::of(-5, 2)->round());
    }

    /**
     * @return iterable<string, array{int, int, RoundingMode, int}>
     */
    public static function roots(): iterable
    {
        yield 'zero' => [0, 1, RoundingMode::PositiveInfinity, 0];
        yield 'perfect square' => [49, 1, RoundingMode::PositiveInfinity, 7];
        yield 'perfect square of a fraction' => [9, 4, RoundingMode::HalfAwayFromZero, 2];
        yield 'two, nearest' => [2, 1, RoundingMode::HalfAwayFromZero, 1];
        yield 'two, up' => [2, 1, RoundingMode::PositiveInfinity, 2];
        yield 'three, nearest' => [3, 1, RoundingMode::HalfAwayFromZero, 2];
        yield 'three, down' => [3, 1, RoundingMode::TowardsZero, 1];
        yield 'eight, nearest' => [8, 1, RoundingMode::HalfAwayFromZero, 3];
        yield 'one quarter, half away' => [1, 4, RoundingMode::HalfAwayFromZero, 1];
        yield 'one quarter, half towards' => [1, 4, RoundingMode::HalfTowardsZero, 0];
        yield 'one quarter, half even' => [1, 4, RoundingMode::HalfEven, 0];
        yield 'nine quarters plus' => [25, 4, RoundingMode::HalfEven, 2];
        yield 'nine quarters plus, odd' => [25, 4, RoundingMode::HalfOdd, 3];
        yield 'large' => [1_000_000_000_000, 1, RoundingMode::HalfAwayFromZero, 1_000_000];
        yield 'large minus one' => [999_999_999_999, 1, RoundingMode::NegativeInfinity, 999_999];
    }

    #[DataProvider('roots')]
    public function testSquareRootsAreRoundedExactly(int $numerator, int $denominator, RoundingMode $mode, int $expected): void
    {
        self::assertSame($expected, Ratio::of($numerator, $denominator)->sqrtRounded($mode));
    }

    public function testSquareRootDefaultsToHalfAwayFromZero(): void
    {
        self::assertSame(1, Ratio::of(1, 4)->sqrtRounded());
    }

    public function testANegativeRatioHasNoSquareRoot(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('A negative ratio has no square root.');

        Ratio::of(-1)->sqrtRounded();
    }

    public function testDecimalsAreForDisplay(): void
    {
        self::assertSame('2.333', Ratio::of(7, 3)->toDecimal(3));
        self::assertSame('2.33', Ratio::of(7, 3)->toDecimal());
        self::assertSame('-0.05', Ratio::of(-1, 20)->toDecimal());
        self::assertSame('0.50', Ratio::of(1, 2)->toDecimal());
        self::assertSame('3', Ratio::of(5, 2)->toDecimal(0));
        self::assertSame('2', Ratio::of(5, 2)->toDecimal(0, RoundingMode::HalfEven));
        self::assertSame('12.00', Ratio::of(12)->toDecimal());
    }

    public function testAProductTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('A product is larger than exact arithmetic allows.');

        Ratio::of(\PHP_INT_MAX)->times(Ratio::of(2));
    }

    public function testAProductAtTheEdgeIsKept(): void
    {
        self::assertSame(\PHP_INT_MAX - 1, Ratio::of(intdiv(\PHP_INT_MAX, 2))->times(Ratio::of(2))->numerator);
        self::assertSame(0, Ratio::of(0)->times(Ratio::of(\PHP_INT_MAX))->numerator);
    }

    public function testASumTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('A sum is larger than exact arithmetic allows.');

        Ratio::of(\PHP_INT_MAX)->plus(Ratio::of(1));
    }

    public function testADifferenceTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('A sum is larger than exact arithmetic allows.');

        Ratio::of(-\PHP_INT_MAX)->minus(Ratio::of(1));
    }

    public function testSumsAtTheEdgeAreKept(): void
    {
        self::assertSame(\PHP_INT_MAX, Ratio::of(\PHP_INT_MAX - 1)->plus(Ratio::of(1))->numerator);
        self::assertSame(-\PHP_INT_MAX, Ratio::of(-\PHP_INT_MAX + 1)->minus(Ratio::of(1))->numerator);
    }

    public function testSquareRootsOfAQuotientBeyondTheBitsOfAFloatAreExact(): void
    {
        // The float root of 2_249_999_999_999_999_999 is 1_500_000_000 although the integer root is one less.
        $ratio = Ratio::of(2_249_999_999_999_999_999);

        self::assertSame(1_499_999_999, $ratio->sqrtRounded(RoundingMode::TowardsZero));
        self::assertSame(1_499_999_999, $ratio->sqrtRounded(RoundingMode::NegativeInfinity));
        self::assertSame(1_500_000_000, $ratio->sqrtRounded(RoundingMode::PositiveInfinity));
        self::assertSame(1_500_000_000, $ratio->sqrtRounded());
        self::assertSame(1_500_000_000, Ratio::of(2_250_000_000_000_000_000)->sqrtRounded(RoundingMode::TowardsZero));
    }

    public function testNoDecimalOfZeroIsSigned(): void
    {
        self::assertSame('0.00', Ratio::of(0)->toDecimal());
        self::assertSame('0.00', Ratio::of(-1, 1000)->toDecimal());
        self::assertSame('0', Ratio::of(0)->toDecimal(0));
        self::assertSame('0', Ratio::of(-1, 3)->toDecimal(0));
    }

    public function testDecimalsOfAWholeScaleKeepTheirSign(): void
    {
        self::assertSame('-3', Ratio::of(-5, 2)->toDecimal(0));
        self::assertSame('-2', Ratio::of(-5, 2)->toDecimal(0, RoundingMode::HalfEven));
        self::assertSame('-1.5', Ratio::of(-3, 2)->toDecimal(1));
    }

    public function testANegativeSumTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('A sum is larger than exact arithmetic allows.');

        Ratio::of(-\PHP_INT_MAX)->plus(Ratio::of(-1));
    }
}
