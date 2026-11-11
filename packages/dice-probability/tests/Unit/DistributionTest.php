<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Range;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Estimate;
use Tilttek\DiceProbability\EstimateReason;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;
use Tilttek\DiceProbability\Outcome;

#[CoversClass(Distribution::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(Estimate::class)]
#[CoversClass(EstimateReason::class)]
final class DistributionTest extends TestCase
{
    public function testTheProbabilitiesOfTwoDice(): void
    {
        $distribution = $this->twoDice();

        self::assertSame([2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12], $distribution->values());
        self::assertSame('36', (string) $distribution->total);
        self::assertSame(2, $distribution->minimum());
        self::assertSame(12, $distribution->maximum());
        self::assertSame('6/36', (string) $distribution->probability(7));
        self::assertSame('0/36', (string) $distribution->probability(13));
        self::assertSame('21/36', (string) $distribution->atLeast(7));
        self::assertSame('21/36', (string) $distribution->atMost(7));
        self::assertSame('36/36', (string) $distribution->atLeast(2));
        self::assertSame('1/36', (string) $distribution->atMost(2));
        self::assertSame('0/36', (string) $distribution->atLeast(13));
        self::assertSame('6/36', (string) $distribution->matching(new Range(10, 12)));
        self::assertSame('3/36', (string) $distribution->matching(new Comparison(Comparator::Greater, 10)));
        self::assertSame('7/1', (string) $distribution->mean()->reduced());
        self::assertSame('35/6', (string) $distribution->variance()->reduced());
        self::assertEqualsWithDelta(2.41523, $distribution->standardDeviation(), 1e-5);
        self::assertFalse($distribution->isEstimated());
        self::assertNull($distribution->estimate);
        self::assertTrue($distribution->remaining->isZero());
    }

    public function testEachOutcomeIsARowOfATable(): void
    {
        $outcomes = $this->twoDice()->outcomes();

        self::assertCount(11, $outcomes);
        self::assertSame(2, $outcomes[0]->value);
        self::assertSame('1/36', (string) $outcomes[0]->probability);
        self::assertSame('36/36', (string) $outcomes[0]->atLeast);
        self::assertSame('1/36', (string) $outcomes[0]->atMost);
        self::assertSame(7, $outcomes[5]->value);
        self::assertSame('6/36', (string) $outcomes[5]->probability);
        self::assertSame('21/36', (string) $outcomes[5]->atLeast);
        self::assertSame('21/36', (string) $outcomes[5]->atMost);
        self::assertSame('1/36', (string) $outcomes[10]->atLeast);
        self::assertSame('36/36', (string) $outcomes[10]->atMost);
    }

    public function testNegativeValuesHaveASignedMean(): void
    {
        $distribution = new Distribution([-3 => Natural::of(2), 1 => Natural::one(), 0 => Natural::zero()]);

        self::assertSame([-3, 1], $distribution->values());
        self::assertSame('-5/3', (string) $distribution->mean());
        self::assertSame('32/9', (string) $distribution->variance()->reduced());
        self::assertSame('3', (string) $distribution->total);
    }

    public function testAnEstimateSaysItsMargin(): void
    {
        $distribution = new Distribution([1 => Natural::of(3), 2 => Natural::one()], Fraction::of(1, 10), new Estimate(20_000, 5, EstimateReason::TooLarge));

        self::assertTrue($distribution->isEstimated());
        self::assertSame('1/10', (string) $distribution->remaining);
        self::assertEqualsWithDelta(0.00693, $distribution->estimate?->margin(), 1e-5);
        self::assertSame(0.49, new Estimate(4, 0, EstimateReason::Unsupported)->margin());
        self::assertSame(0.98, new Estimate(1, 0, EstimateReason::Unsupported)->margin());
        self::assertSame('too-large', EstimateReason::TooLarge->value);
        self::assertSame('unsupported', EstimateReason::Unsupported->value);
    }

    public function testAnEstimateHasSamples(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('An estimate is drawn from at least one sample; 0 is not.');

        new Estimate(0, 0, EstimateReason::TooLarge);
    }

    public function testADistributionHasAValue(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A distribution has at least one value of some weight.');

        new Distribution([3 => Natural::zero()]);
    }

    private function twoDice(): Distribution
    {
        $weights = [];

        foreach ([12 => 1, 2 => 1, 3 => 2, 4 => 3, 5 => 4, 6 => 5, 7 => 6, 8 => 5, 9 => 4, 10 => 3, 11 => 2] as $value => $ways) {
            $weights[$value] = Natural::of($ways);
        }

        return new Distribution($weights);
    }
}
