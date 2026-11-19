<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Unit\Statistics;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\DiceJail\Exception\InvalidRolls;
use Tilttek\DiceJail\Statistics\ChiSquared;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Statistics\FaceCount;
use Tilttek\DiceJail\Statistics\FitResult;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;
use Tilttek\DiceJail\Statistics\Tally;
use Tilttek\DiceJail\Statistics\Verdict;

#[CoversClass(GoodnessOfFit::class)]
#[CoversClass(FitResult::class)]
#[CoversClass(FaceCount::class)]
#[CoversClass(Verdict::class)]
#[CoversClass(InvalidRolls::class)]
final class GoodnessOfFitTest extends TestCase
{
    /**
     * Thirty rolls of a three-faced die, ten expected per face: the statistic is exact, and two degrees of freedom give
     * the p-value e^(−x/2).
     *
     * @return iterable<string, array{list<int>, float, Verdict}>
     */
    public static function threeFacedDice(): iterable
    {
        yield 'perfectly even' => [[10, 10, 10], 0.0, Verdict::NothingUnusual];
        yield 'uneven, within chance' => [[15, 10, 5], 5.0, Verdict::NothingUnusual];
        yield 'somewhat unusual' => [[18, 8, 4], 10.4, Verdict::SomewhatUnusual];
        yield 'very unusual' => [[20, 7, 3], 15.8, Verdict::VeryUnusual];
    }

    /**
     * @param list<int> $counts
     */
    #[DataProvider('threeFacedDice')]
    public function testItComputesTheExactStatisticAndPValue(array $counts, float $statistic, Verdict $verdict): void
    {
        $result = new GoodnessOfFit()->test($this->tally($counts), ExpectedFaces::uniform(3));

        self::assertSame(30, $result->rolls);
        self::assertSame(2, $result->degreesOfFreedom);
        self::assertEqualsWithDelta($statistic, $result->statistic, 1.0E-12);
        self::assertEqualsWithDelta(exp(-$statistic / 2), (float) $result->pValue, 1.0E-15);
        self::assertSame($verdict, $result->verdict);
        self::assertSame(15, $result->minimumRolls);
        self::assertTrue($result->isConclusive());
        self::assertSame(0, $result->rollsStillNeeded());
        self::assertEqualsWithDelta(2.0, $result->expectedMean, 1.0E-12);
        self::assertEqualsWithDelta(($counts[0] + 2 * $counts[1] + 3 * $counts[2]) / 30, $result->observedMean, 1.0E-12);
    }

    public function testItListsEveryFaceWithItsObservedAndExpectedCounts(): void
    {
        $result = new GoodnessOfFit()->test(Tally::of([1, 1, 1, 3, 3, 3, 3, 3, 3, 3, 3, 3]), ExpectedFaces::uniform(3));

        self::assertEquals([new FaceCount(1, 3, 4.0), new FaceCount(2, 0, 4.0), new FaceCount(3, 9, 4.0)], $result->faces);
        self::assertSame(-1.0, $result->faces[0]->difference());
        self::assertSame(5.0, $result->faces[2]->difference());
        // (1 + 16 + 25) / 4.
        self::assertEqualsWithDelta(10.5, $result->statistic, 1.0E-12);
    }

    public function testFiveFacesHaveFourDegreesOfFreedom(): void
    {
        $result = new GoodnessOfFit()->test($this->tally([9, 5, 5, 3, 3]), ExpectedFaces::uniform(5));

        self::assertSame(4, $result->degreesOfFreedom);
        self::assertEqualsWithDelta(4.8, $result->statistic, 1.0E-12);
        self::assertEqualsWithDelta(exp(-2.4) * 3.4, (float) $result->pValue, 1.0E-15);
        self::assertSame(25, $result->minimumRolls);
    }

    public function testATestOnTooFewRollsGivesNoPValue(): void
    {
        $result = new GoodnessOfFit()->test($this->tally([5, 5, 5, 5, 5, 4]), ExpectedFaces::uniform(6));

        self::assertSame(29, $result->rolls);
        self::assertNull($result->pValue);
        self::assertSame(Verdict::NotEnoughRolls, $result->verdict);
        self::assertFalse($result->isConclusive());
        self::assertSame(30, $result->minimumRolls);
        self::assertSame(1, $result->rollsStillNeeded());
        self::assertGreaterThan(0.0, $result->statistic);
    }

    public function testTheTestIsTrustedFromTheMinimumOn(): void
    {
        $result = new GoodnessOfFit()->test($this->tally([5, 5, 5, 5, 5, 5]), ExpectedFaces::uniform(6));

        self::assertSame(Verdict::NothingUnusual, $result->verdict);
        self::assertSame(1.0, $result->pValue);
    }

    public function testNoRollsAtAll(): void
    {
        $result = new GoodnessOfFit()->test(Tally::of([]), ExpectedFaces::uniform(20));

        self::assertSame(0, $result->rolls);
        self::assertSame(0.0, $result->statistic);
        self::assertSame(0.0, $result->observedMean);
        self::assertSame(100, $result->rollsStillNeeded());
        self::assertSame(Verdict::NotEnoughRolls, $result->verdict);
    }

    public function testAFaceTheDieCannotShowIsRefused(): void
    {
        try {
            new GoodnessOfFit()->test(Tally::of([1, 7]), ExpectedFaces::uniform(6));
            self::fail('No exception was thrown.');
        } catch (InvalidRolls $invalidRolls) {
            self::assertSame('The rolls hold a 7, a face the die cannot show: they belong to another die.', $invalidRolls->getMessage());
        }
    }

    public function testFairDiceCrossTheLinesAsOftenAsTheLinesSay(): void
    {
        // 2 000 fair d6, 60 rolls each, from a fixed seed: about 1 % fall below 0.01 and 0.1 % below 0.001, no more.
        $randomizer = new Randomizer(new Xoshiro256StarStar(20261113));
        $test = new GoodnessOfFit();
        $somewhat = 0;
        $very = 0;

        for ($die = 0; $die < 2_000; ++$die) {
            $rolls = [];

            for ($i = 0; $i < 60; ++$i) {
                $rolls[] = $randomizer->getInt(1, 6);
            }

            $verdict = $test->test(Tally::of($rolls), ExpectedFaces::uniform(6))->verdict;
            $somewhat += Verdict::NothingUnusual === $verdict ? 0 : 1;
            $very += Verdict::VeryUnusual === $verdict ? 1 : 0;
        }

        self::assertLessThan(45, $somewhat);
        self::assertLessThan(8, $very);
    }

    public function testAHeavilyBiasedDieIsFoundVeryUnusual(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(7));
        $rolls = [];

        for ($i = 0; $i < 600; ++$i) {
            // One roll in four is a 1: a die worn on one edge.
            $rolls[] = 0 === $randomizer->getInt(0, 3) ? 1 : $randomizer->getInt(1, 6);
        }

        self::assertSame(Verdict::VeryUnusual, new GoodnessOfFit()->test(Tally::of($rolls), ExpectedFaces::uniform(6))->verdict);
    }

    public function testTheVerdictLinesAreStrict(): void
    {
        self::assertSame(Verdict::NothingUnusual, Verdict::ofPValue(0.01));
        self::assertSame(Verdict::SomewhatUnusual, Verdict::ofPValue(0.0099999));
        self::assertSame(Verdict::SomewhatUnusual, Verdict::ofPValue(0.001));
        self::assertSame(Verdict::VeryUnusual, Verdict::ofPValue(0.00099999));
        self::assertSame(Verdict::NothingUnusual, Verdict::ofPValue(ChiSquared::upperTail(0.0, 1)));
    }

    /**
     * @param list<int> $counts the count of each face from 1
     */
    private function tally(array $counts): Tally
    {
        $faces = [];

        foreach ($counts as $index => $count) {
            array_push($faces, ...array_fill(0, $count, $index + 1));
        }

        return Tally::of($faces);
    }
}
