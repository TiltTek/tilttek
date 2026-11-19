<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Unit\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceJail\Exception\InvalidRolls;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Estimate;
use Tilttek\DiceProbability\EstimateReason;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;
use Tilttek\DiceProbability\Probabilities;

#[CoversClass(ExpectedFaces::class)]
#[CoversClass(InvalidRolls::class)]
final class ExpectedFacesTest extends TestCase
{
    public function testAUniformDieGivesEachFaceAnEqualShare(): void
    {
        $d6 = ExpectedFaces::uniform(6);

        self::assertSame([1, 2, 3, 4, 5, 6], array_keys($d6->probabilities));
        self::assertSame('1/6', (string) $d6->probabilities[4]);
        self::assertSame(6, $d6->faces());
        self::assertTrue($d6->has(1));
        self::assertTrue($d6->has(6));
        self::assertFalse($d6->has(0));
        self::assertFalse($d6->has(7));
        self::assertEqualsWithDelta(3.5, $d6->mean(), 1.0E-12);
        self::assertSame(30, $d6->minimumRolls(5));
        self::assertSame(6, $d6->minimumRolls(1));
    }

    public function testARangeMayStartAnywhere(): void
    {
        $fudge = ExpectedFaces::range(-1, 1);

        self::assertSame([-1, 0, 1], array_keys($fudge->probabilities));
        self::assertSame('1/3', (string) $fudge->probabilities[0]);
        self::assertEqualsWithDelta(0.0, $fudge->mean(), 1.0E-12);
        self::assertSame(15, $fudge->minimumRolls(5));
        self::assertSame(2, ExpectedFaces::range(0, 1)->faces());
        self::assertSame(1_000, ExpectedFaces::uniform(1_000)->faces());
    }

    public function testADistributionGivesItsExactProbabilities(): void
    {
        $twoD6 = ExpectedFaces::fromDistribution(new Probabilities()->distribution(new DiceRoller()->parse('2d6')));

        self::assertSame(range(2, 12), array_keys($twoD6->probabilities));
        self::assertSame('1/6', (string) $twoD6->probabilities[7]);
        self::assertSame('1/36', (string) $twoD6->probabilities[12]);
        self::assertEqualsWithDelta(7.0, $twoD6->mean(), 1.0E-12);
        self::assertSame(180, $twoD6->minimumRolls(5));
    }

    public function testTheMinimumRoundsUpToWholeRolls(): void
    {
        // Faces of 2/7 and 5/7: five times the rarer one needs 17.5 rolls, so 18.
        $weighted = ExpectedFaces::fromDistribution(new Distribution([1 => Natural::of(2), 2 => Natural::of(5)]));

        self::assertSame(18, $weighted->minimumRolls(5));
        self::assertSame(4, $weighted->minimumRolls(1));
        self::assertSame(7, $weighted->minimumRolls(2));
        self::assertEqualsWithDelta(12 / 7, $weighted->mean(), 1.0E-12);
    }

    public function testAFaceAsRareAsTheLimitAllowsIsKept(): void
    {
        $rare = ExpectedFaces::fromDistribution(new Distribution([1 => Natural::of(1), 2 => Natural::of(199_999)]));

        self::assertSame(1_000_000, $rare->minimumRolls(5));
    }

    public function testAFaceRarerThanTheLimitIsRefused(): void
    {
        $rare = ExpectedFaces::fromDistribution(new Distribution([1 => Natural::of(1), 2 => Natural::of(200_000)]));

        try {
            $rare->minimumRolls(5);
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('A face of the die is so rare that no reasonable number of rolls could judge it.', $invalidRolls->getMessage());
        }
    }

    public function testAnEstimatedDistributionIsRefused(): void
    {
        try {
            ExpectedFaces::fromDistribution(new Distribution([1 => Natural::of(1), 2 => Natural::of(1)], estimate: new Estimate(10, 0, EstimateReason::TooLarge)));
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('The distribution is estimated from samples: a goodness-of-fit test needs exact probabilities.', $invalidRolls->getMessage());
        }
    }

    public function testATruncatedDistributionIsRefused(): void
    {
        try {
            ExpectedFaces::fromDistribution(new Distribution([1 => Natural::of(1), 2 => Natural::of(1)], Fraction::of(1, 100)));
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('The distribution cuts its explosions at a depth: a goodness-of-fit test needs every outcome.', $invalidRolls->getMessage());
        }
    }

    public function testADistributionOfOneValueIsRefused(): void
    {
        try {
            ExpectedFaces::fromDistribution(new Distribution([4 => Natural::of(1)]));
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('A die judged by the statistics has at least two faces that can come up.', $invalidRolls->getMessage());
        }
    }

    public function testADieOfOneFaceIsRefused(): void
    {
        try {
            ExpectedFaces::uniform(1);
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('A die judged by the statistics has at least two faces that can come up.', $invalidRolls->getMessage());
        }
    }

    public function testADieOfTooManyFacesIsRefused(): void
    {
        try {
            ExpectedFaces::uniform(1_001);
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('A die judged by the statistics has at most 1000 faces.', $invalidRolls->getMessage());
        }
    }

    public function testAnEmptyRangeIsRefused(): void
    {
        try {
            ExpectedFaces::range(3, 2);
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('The faces from 3 to 2 hold no face.', $invalidRolls->getMessage());
        }
    }

    public function testARangeOfOneFaceIsRefused(): void
    {
        try {
            ExpectedFaces::range(3, 3);
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('A die judged by the statistics has at least two faces that can come up.', $invalidRolls->getMessage());
        }
    }
}
