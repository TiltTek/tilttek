<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Unit\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceJail\Statistics\ChiSquared;

#[CoversClass(ChiSquared::class)]
final class ChiSquaredTest extends TestCase
{
    /**
     * The quantiles of the chi-squared distribution found in every table, to their published precision.
     *
     * @return iterable<string, array{float, int, float}>
     */
    public static function quantiles(): iterable
    {
        yield '5 % at one degree' => [3.841458820694124, 1, 0.05];
        yield '1 % at one degree' => [6.634896601021214, 1, 0.01];
        yield '5 % at five degrees, a d6' => [11.070497693516351, 5, 0.05];
        yield '5 % at nine degrees, a d10' => [16.918977604620448, 9, 0.05];
        yield '5 % at nineteen degrees, a d20' => [30.14352720564616, 19, 0.05];
        yield '0.1 % at nineteen degrees, a d20' => [43.82019596451, 19, 0.001];
    }

    #[DataProvider('quantiles')]
    public function testItGivesThePublishedQuantiles(float $statistic, int $degrees, float $pValue): void
    {
        self::assertEqualsWithDelta($pValue, ChiSquared::upperTail($statistic, $degrees), $pValue * 1.0E-9);
    }

    /**
     * Two degrees of freedom have the closed form e^(−x/2), four have e^(−x/2)(1 + x/2), six e^(−y)(1 + y + y²/2).
     *
     * @return iterable<string, array{float, int, float}>
     */
    public static function closedForms(): iterable
    {
        foreach ([0.1, 1.0, 2.0, 2.9, 3.0, 5.0, 10.0, 15.8, 40.0, 120.0] as $x) {
            yield 'two degrees, '.$x => [$x, 2, exp(-$x / 2)];
            yield 'four degrees, '.$x => [$x, 4, exp(-$x / 2) * (1 + $x / 2)];
        }

        foreach ([0.5, 1.0, 4.0, 9.0, 25.0] as $x) {
            yield 'six degrees, '.$x => [$x, 6, exp(-$x / 2) * (1 + $x / 2 + ($x / 2) ** 2 / 2)];
        }
    }

    #[DataProvider('closedForms')]
    public function testItMatchesTheClosedFormsOfEvenDegrees(float $statistic, int $degrees, float $pValue): void
    {
        self::assertEqualsWithDelta($pValue, ChiSquared::upperTail($statistic, $degrees), $pValue * 1.0E-12);
    }

    public function testAStatisticOfZeroOrLessIsCertain(): void
    {
        self::assertSame(1.0, ChiSquared::upperTail(0.0, 5));
        self::assertSame(1.0, ChiSquared::upperTail(-3.0, 5));
    }

    public function testATinyStatisticIsAlmostCertainAndAHugeOneAlmostImpossible(): void
    {
        self::assertEqualsWithDelta(0.9999747686747843, ChiSquared::upperTail(1.0E-9, 1), 1.0E-12);
        self::assertLessThan(1.0E-30, ChiSquared::upperTail(200.0, 19));
        self::assertGreaterThan(0.0, ChiSquared::upperTail(200.0, 19));
        self::assertEqualsWithDelta(1.0, ChiSquared::upperTail(0.5, 30), 1.0E-15);
        self::assertSame(0.0, ChiSquared::upperTail(5000.0, 3));
    }

    public function testTheTailIsBoundedAndFallsAsTheStatisticGrows(): void
    {
        foreach ([1, 2, 3, 5, 9, 19, 99, 999] as $degrees) {
            $previous = 1.0;

            for ($x = 0.25; $x < 3 * $degrees + 40; $x *= 1.5) {
                $tail = ChiSquared::upperTail($x, $degrees);
                self::assertGreaterThanOrEqual(0.0, $tail);
                self::assertLessThanOrEqual($previous, $tail, \sprintf('At %d degrees, x = %f.', $degrees, $x));
                $previous = $tail;
            }
        }
    }

    public function testTheMedianOfManyDegreesIsNearTheirNumber(): void
    {
        // The median of the chi-squared law is close to k(1 − 2/(9k))³.
        foreach ([99, 999] as $degrees) {
            $median = $degrees * (1 - 2 / (9 * $degrees)) ** 3;
            self::assertEqualsWithDelta(0.5, ChiSquared::upperTail($median, $degrees), 0.001);
        }
    }

    public function testTheLogarithmOfGammaIsExactForWholeAndHalfWholeArguments(): void
    {
        self::assertSame(0.0, ChiSquared::logGamma(2));
        self::assertSame(0.0, ChiSquared::logGamma(4));
        self::assertEqualsWithDelta(log(2), ChiSquared::logGamma(6), 1.0E-15);
        self::assertEqualsWithDelta(log(120), ChiSquared::logGamma(12), 1.0E-14);
        self::assertEqualsWithDelta(0.5 * log(\M_PI), ChiSquared::logGamma(1), 1.0E-15);
        self::assertEqualsWithDelta(log(sqrt(\M_PI) / 2), ChiSquared::logGamma(3), 1.0E-15);
        self::assertEqualsWithDelta(log(3 * sqrt(\M_PI) / 4), ChiSquared::logGamma(5), 1.0E-15);
    }
}
