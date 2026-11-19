<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Computation\Budget;
use Tilttek\DiceProbability\Computation\Calculator;
use Tilttek\DiceProbability\Computation\Weights;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Probabilities;

/**
 * Properties that tie the parser, the exact distribution and the roller together, over expressions generated from a
 * fixed seed: the bounds of the distribution are those the expression allows, and every total the roller gives from
 * the parsed expression is a value of its distribution — so that parse, distribution and roll read the same thing.
 */
#[CoversClass(Probabilities::class)]
#[CoversClass(Calculator::class)]
#[CoversClass(Weights::class)]
#[CoversClass(Budget::class)]
#[CoversClass(Distribution::class)]
final class AgreementPropertyTest extends TestCase
{
    /** The generated expressions per seed. */
    private const int CASES = 60;

    /** The rolls of each expression. */
    private const int ROLLS = 60;

    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach ([3, 19, 53, 2026] as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    #[DataProvider('seeds')]
    public function testEveryRollIsAValueOfTheDistributionWithinItsBounds(int $seed): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar($seed));
        $dice = new DiceRoller();
        $probabilities = new Probabilities();

        for ($case = 0; $case < self::CASES; ++$case) {
            [$expression, $lowest, $highest] = $this->expression($randomizer);
            $pool = $dice->parse($expression);
            $distribution = $probabilities->distribution($pool);

            self::assertFalse($distribution->isEstimated(), $expression);
            self::assertTrue($distribution->remaining->isZero(), $expression);
            self::assertSame($lowest, $distribution->minimum(), $expression);
            self::assertSame($highest, $distribution->maximum(), $expression);

            $values = array_flip($distribution->values());

            for ($roll = 0; $roll < self::ROLLS; ++$roll) {
                $total = $dice->roll($pool, new Randomizer(new Xoshiro256StarStar($seed * 10_000 + $case * 100 + $roll)))->totals()[0];

                self::assertIsInt($total, $expression);
                self::assertArrayHasKey($total, $values, \sprintf('%s rolled %d, which its distribution does not hold.', $expression, $total));
            }
        }
    }

    /**
     * An expression the exact computation follows, and its lowest and highest totals: sums and differences of sets of
     * dice, some keeping the highest or lowest of them or rerolling their ones, and of numbers.
     *
     * @return array{string, int, int}
     */
    private function expression(Randomizer $randomizer): array
    {
        $expression = '';
        $lowest = 0;
        $highest = 0;

        for ($terms = $randomizer->getInt(1, 3); $terms > 0; --$terms) {
            $sign = '' === $expression || 0 === $randomizer->getInt(0, 2) ? 1 : -1;
            [$term, $low, $high] = $this->term($randomizer);
            $expression .= ('' === $expression ? '' : (1 === $sign ? '+' : '-')).$term;
            $lowest += 1 === $sign ? $low : -$high;
            $highest += 1 === $sign ? $high : -$low;
        }

        return [$expression, $lowest, $highest];
    }

    /**
     * @return array{string, int, int}
     */
    private function term(Randomizer $randomizer): array
    {
        $count = $randomizer->getInt(1, 4);
        $sides = [2, 3, 4, 6, 8, 10][$randomizer->getInt(0, 5)];

        return match ($randomizer->getInt(0, 4)) {
            0 => [(string) ($number = $randomizer->getInt(0, 9)), $number, $number],
            1 => [\sprintf('%dd%dkh%d', $count, $sides, $kept = $randomizer->getInt(1, $count)), $kept, $kept * $sides],
            2 => [\sprintf('%dd%dkl%d', $count, $sides, $kept = $randomizer->getInt(1, $count)), $kept, $kept * $sides],
            3 => [\sprintf('%dd%dr1', $count, $sides), 2 * $count, $sides * $count],
            default => [\sprintf('%dd%d', $count, $sides), $count, $count * $sides],
        };
    }
}
