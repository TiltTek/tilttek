<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\DiceProbability\Computation\Budget;
use Tilttek\DiceProbability\Computation\Calculator;
use Tilttek\DiceProbability\Computation\Multisets;
use Tilttek\DiceProbability\Computation\Sampler;
use Tilttek\DiceProbability\Computation\TooLarge;
use Tilttek\DiceProbability\Computation\Unsupported;
use Tilttek\DiceProbability\Computation\Weights;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Estimate;
use Tilttek\DiceProbability\EstimateReason;
use Tilttek\DiceProbability\Event\AllEqual;
use Tilttek\DiceProbability\Exception\CannotCompute;
use Tilttek\DiceProbability\Outcome;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbability\Settings;
use Tilttek\DiceProbability\Tests\Unit\Fixture\LoadedDie;
use Tilttek\DiceProbability\Tests\Unit\Fixture\Oracle;

#[CoversClass(Probabilities::class)]
#[CoversClass(Calculator::class)]
#[CoversClass(Weights::class)]
#[CoversClass(Budget::class)]
#[CoversClass(Sampler::class)]
#[CoversClass(Multisets::class)]
#[CoversClass(TooLarge::class)]
#[CoversClass(Unsupported::class)]
#[CoversClass(Distribution::class)]
#[CoversClass(Outcome::class)]
#[CoversClass(Estimate::class)]
#[CoversClass(CannotCompute::class)]
final class ProbabilitiesTest extends TestCase
{
    /**
     * @param list<int> $faces
     */
    #[DataProvider('judgedSets')]
    public function testASetOfDiceMatchesEveryRollJudgedByTheRoller(string $expression, array $faces): void
    {
        $pool = new DiceRoller()->parse($expression);
        $dice = $pool->expressions[0];
        self::assertInstanceOf(Dice::class, $dice);

        $distribution = new Probabilities()->distribution($pool);

        self::assertFalse($distribution->isEstimated());
        self::assertSame(Oracle::weights($dice, $faces), $this->weights($distribution));
    }

    /**
     * @return iterable<string, array{string, list<int>}>
     */
    public static function judgedSets(): iterable
    {
        $d6 = [1, 2, 3, 4, 5, 6];

        yield 'sum' => ['3d6', $d6];
        yield 'keep highest' => ['4d6kh3', $d6];
        yield 'keep lowest' => ['4d6kl1', $d6];
        yield 'drop highest' => ['4d6dh1', $d6];
        yield 'drop lowest' => ['4d6dl2', $d6];
        yield 'keep all' => ['3d6kh3', $d6];
        yield 'floor' => ['3d6min2', $d6];
        yield 'ceiling' => ['3d6max4', $d6];
        yield 'floor before selection' => ['4d6min2kh3', $d6];
        yield 'transformation' => ['3d6t[>4:10]', $d6];
        yield 'first transformation' => ['3d6t[>4:10,>2:7]', $d6];
        yield 'transformation of the die as it fell' => ['3d6min3t[die:1:9]', $d6];
        yield 'transformation then selection' => ['5d4t[1:4]kh2', [1, 2, 3, 4]];
        yield 'filter' => ['4d6f[>2]', $d6];
        yield 'filter of the die as it fell' => ['3d6min3f[die:>1]', $d6];
        yield 'filter then keep' => ['4d6f[>2]kh2', $d6];
        yield 'filter then drop' => ['4d6f[!=6]dl1', $d6];
        yield 'filter then drop highest' => ['4d6f[<5]dh1', $d6];
        yield 'successes' => ['3d6>=5', $d6];
        yield 'successes and failures' => ['4d6>=5f1', $d6];
        yield 'successes of the die as it fell' => ['4d6min3c[die:3]', $d6];
        yield 'successes among the kept' => ['4d6c[>=5]kh2', $d6];
        yield 'successes among the dropped' => ['4d6c[>=5]dh2', $d6];
        yield 'failures among the kept' => ['4d6>=5f1kl3', $d6];
        yield 'all' => ['3d6c[all:>2]', $d6];
        yield 'all among the kept' => ['4d6c[all:>2]kh2', $d6];
        yield 'any' => ['3d6c[any:6]', $d6];
        yield 'total' => ['3d6c[total:>=12]', $d6];
        yield 'total of the kept' => ['4d6c[total:>=12]kh3', $d6];
        yield 'all of none' => ['3d6f[>6]c[all:>2]', $d6];
        yield 'marks and order' => ['3d6cs cf sd', $d6];
        yield 'weighted Fudge dice' => ['4dF.1kh2', [-1, 0, 0, 0, 0, 1]];
        yield 'Fudge dice' => ['4dF', [-1, 0, 1]];
        yield 'range' => ['3d[0..3]kh2', [0, 1, 2, 3]];
        yield 'negative range' => ['3d[-2..1]kl2', [-2, -1, 0, 1]];
    }

    /**
     * @param array<int, string> $weights
     */
    #[DataProvider('computedExpressions')]
    public function testAnExpressionIsComputedExactly(string $expression, array $weights, RoundingMode $rounding = RoundingMode::NegativeInfinity): void
    {
        $distribution = new Probabilities()->distribution(new DiceRoller()->parse($expression), $rounding);

        self::assertFalse($distribution->isEstimated());
        self::assertSame($weights, $this->weights($distribution));
        self::assertTrue($distribution->remaining->isZero());
    }

    /**
     * @return iterable<string, array{0: string, 1: array<int, string>, 2?: RoundingMode}>
     */
    public static function computedExpressions(): iterable
    {
        yield 'constant' => ['7', [7 => '1']];
        yield 'two dice' => ['2d6', [2 => '1', 3 => '2', 4 => '3', 5 => '4', 6 => '5', 7 => '6', 8 => '5', 9 => '4', 10 => '3', 11 => '2', 12 => '1']];
        yield 'a die and a constant' => ['1d4+2', [3 => '1', 4 => '1', 5 => '1', 6 => '1']];
        yield 'subtraction' => ['1d4-1d2', [-1 => '1', 0 => '2', 1 => '2', 2 => '2', 3 => '1']];
        yield 'multiplication' => ['1d3*2', [2 => '1', 4 => '1', 6 => '1']];
        yield 'negation' => ['-1d3', [-3 => '1', -2 => '1', -1 => '1']];
        yield 'division rounded down' => ['1d6/3', [0 => '2', 1 => '3', 2 => '1']];
        yield 'division rounded up' => ['1d6/3', [1 => '3', 2 => '3'], RoundingMode::PositiveInfinity];
        yield 'floor inside, whatever the roll says' => ['floor(1d6/4)', [0 => '3', 1 => '3'], RoundingMode::PositiveInfinity];
        yield 'ceil' => ['ceil(1d6/4)', [1 => '4', 2 => '2']];
        yield 'round' => ['round(1d6/4)', [0 => '1', 1 => '4', 2 => '1']];
        yield 'remainder' => ['1d6%3', [0 => '2', 1 => '2', 2 => '2']];
        yield 'power' => ['2^1d3', [2 => '1', 4 => '1', 8 => '1']];
        yield 'power rounded' => ['2^(0-1d2)', [0 => '2']];
        yield 'power rounded up' => ['2^(0-1d2)', [1 => '2'], RoundingMode::PositiveInfinity];
        yield 'absolute value' => ['abs(1d4-3)', [0 => '1', 1 => '2', 2 => '1']];
        yield 'smallest' => ['min(1d3, 1d3)', [1 => '5', 2 => '3', 3 => '1']];
        yield 'largest' => ['max(1d2, 1d2, 1d2)', [1 => '1', 2 => '7']];
        yield 'rerolled until it no longer matches' => ['1d6r<3', [3 => '1', 4 => '1', 5 => '1', 6 => '1']];
        yield 'rerolled once' => ['1d6ro1', [1 => '1', 2 => '7', 3 => '7', 4 => '7', 5 => '7', 6 => '7']];
        yield 'rerolled once then kept' => ['2d3ro1kh1', [1 => '1', 2 => '24', 3 => '56']];
        yield 'count rolled first' => ['(1d2)d2', [1 => '2', 2 => '3', 3 => '2', 4 => '1']];
        yield 'sides rolled first' => ['1d(1d2*2)', [1 => '3', 2 => '3', 3 => '1', 4 => '1']];
        yield 'count and sides rolled first' => ['(1d2)d(1d2)', [1 => '6', 2 => '7', 3 => '2', 4 => '1']];
        yield 'list of values' => ['[3,5,8]kh2', [13 => '1']];
        yield 'list and dice' => ['[3,5,8]kh2+1d2', [14 => '1', 15 => '1']];
        yield 'limited explosion' => ['1d6!(1)', [1 => '6', 2 => '6', 3 => '6', 4 => '6', 5 => '6', 7 => '1', 8 => '1', 9 => '1', 10 => '1', 11 => '1', 12 => '1']];
        yield 'explosion on a validator' => ['1d4!>=3(1)', [1 => '4', 2 => '4', 4 => '1', 5 => '2', 6 => '2', 7 => '2', 8 => '1']];
        yield 'compound explosion kept' => ['2d2!!(1)kh1', [1 => '4', 3 => '5', 4 => '7']];
        yield 'explosions limited by a roll' => ['1d2!(1d2)', [1 => '8', 3 => '4', 4 => '2', 5 => '1', 6 => '1']];
        yield 'explosions limited to none by a roll' => ['1d2!(1d2-1)', [1 => '4', 2 => '2', 3 => '1', 4 => '1']];
    }

    public function testAnExplosionIsFollowedToItsDepthAndWhatRemainsIsSaid(): void
    {
        $probabilities = new Probabilities(settings: new Settings(explosionDepth: 2));
        $explosion = $probabilities->distribution(new DiceRoller()->parse('1d6!'));

        self::assertSame([1 => '36', 2 => '36', 3 => '36', 4 => '36', 5 => '36', 7 => '6', 8 => '6', 9 => '6', 10 => '6', 11 => '6', 13 => '1', 14 => '1', 15 => '1', 16 => '1', 17 => '1', 18 => '1'], $this->weights($explosion));
        self::assertSame('1/216', (string) $explosion->remaining);
        self::assertFalse($explosion->isEstimated());

        $compound = $probabilities->distribution(new DiceRoller()->parse('1d6!!'));
        self::assertSame($this->weights($explosion), $this->weights($compound));
        self::assertSame('1/216', (string) $compound->remaining);

        self::assertSame('71/1296', (string) new Probabilities(settings: new Settings(explosionDepth: 1))->distribution(new DiceRoller()->parse('2d6!'))->remaining);
        self::assertSame('1/216', (string) $probabilities->distribution(new DiceRoller()->parse('1d6!(5)'))->remaining);
        self::assertTrue($probabilities->distribution(new DiceRoller()->parse('1d6!(2)'))->remaining->isZero());
        self::assertTrue($probabilities->distribution(new DiceRoller()->parse('1d6!(1)+1d4'))->remaining->isZero());
        self::assertSame('1/216', (string) $probabilities->distribution(new DiceRoller()->parse('1d6!+1d4'))->remaining);
    }

    public function testAPenetratingExplosionTakesOneFromEachDieItAdds(): void
    {
        $distribution = new Probabilities(settings: new Settings(explosionDepth: 1))->distribution(new DiceRoller()->parse('1d6!p'));

        self::assertSame([1 => '6', 2 => '6', 3 => '6', 4 => '6', 5 => '6', 6 => '1', 7 => '1', 8 => '1', 9 => '1', 10 => '1', 11 => '1'], $this->weights($distribution));
        self::assertSame('1/36', (string) $distribution->remaining);

        $compound = new Probabilities(settings: new Settings(explosionDepth: 1))->distribution(new DiceRoller()->parse('1d6!!p'));
        self::assertSame($this->weights($distribution), $this->weights($compound));
    }

    public function testDiceThatExplodeIntoDiceOfTheirOwnAreJudgedOneByOne(): void
    {
        $probabilities = new Probabilities(settings: new Settings(explosionDepth: 2));

        self::assertSame([0 => '180', 1 => '30', 2 => '5', 3 => '1'], $this->weights($probabilities->distribution(new DiceRoller()->parse('1d6!c[=6]'))));
        // A compound explosion is one die: 6 then 2 is an 8, not a 6 and a 2.
        self::assertSame([0 => '216'], $this->weights($probabilities->distribution(new DiceRoller()->parse('1d6!!c[=6]'))));
        self::assertSame([0 => '210', 1 => '6'], $this->weights($probabilities->distribution(new DiceRoller()->parse('1d6!!c[=8]'))));
        // The dice an explosion adds count with a penetration of one: 6 then 6 is a 6 and a 5.
        self::assertSame([0 => '24', 1 => '11', 2 => '1'], $this->weights(new Probabilities(settings: new Settings(explosionDepth: 1))->distribution(new DiceRoller()->parse('1d6!pc[>=5]'))));
        // The filter leaves out the dice below 5, those an explosion adds included.
        self::assertSame([0 => '24', 1 => '10', 2 => '2'], $this->weights(new Probabilities(settings: new Settings(explosionDepth: 1))->distribution(new DiceRoller()->parse('1d6!f[>4]c[>0]'))));
    }

    public function testCustomDiceWhoseFacesCount(): void
    {
        $coin = new DieDefinition('coin', new Face('heads', 'Heads', 1), new Face('tails', 'Tails', 0));
        $weighted = new DieDefinition('weighted', new Face('one', 'One', 1), new Face('also-one', 'One again', 1), new Face('two', 'Two', 2));
        $probabilities = new Probabilities(new CustomDice($coin, $weighted));

        self::assertSame([0 => '1', 1 => '3', 2 => '3', 3 => '1'], $this->weights($probabilities->distribution(new DiceRoller(customDice: new CustomDice($coin))->parse('3d{coin}'))));
        self::assertSame([1 => '2', 2 => '1'], $this->weights($probabilities->distribution(new DiceRoller()->parse('1d{weighted}'))));
    }

    public function testCustomDiceWithoutValuesHaveNoDistribution(): void
    {
        $symbols = new DieDefinition('symbols', new Face('star', 'Star'), new Face('moon', 'Moon'));

        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('The faces of the die "symbols" carry no value: they have no distribution.');

        new Probabilities(new CustomDice($symbols))->distribution(new DiceRoller()->parse('2d{symbols}'));
    }

    public function testAnUnknownDieCannotBeRolled(): void
    {
        try {
            new Probabilities()->distribution(new DiceRoller()->parse('2d{nowhere}'));
            self::fail('An unknown die has no distribution.');
        } catch (CannotCompute $cannotCompute) {
            self::assertSame('An outcome of the expression cannot be rolled: no die named "nowhere" is declared.', $cannotCompute->getMessage());
            self::assertInstanceOf(CannotRoll::class, $cannotCompute->getPrevious());
        }
    }

    public function testADivisionByZeroInAnyOutcomeCannotBeComputed(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('An outcome of the expression cannot be rolled: the expression divides by zero.');

        new Probabilities()->distribution(new DiceRoller()->parse('1d6/(1d2-1)'));
    }

    public function testDiceThatCannotBeRolledOnceTheirCountIsKnown(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('An outcome of the expression cannot be rolled: the dice cannot be rolled once their count and sides are known: an expression rolls at least one die of each kind it names.');

        new Probabilities()->distribution(new DiceRoller()->parse('(1d2-1)d6'));
    }

    public function testAnEstimateFromDiceThatCannotBeRolled(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('An outcome of the expression cannot be rolled: the expression divides by zero.');

        new Probabilities()->distribution(new DiceRoller()->parse('3d6o2/(1d2-1)'));
    }

    public function testAnEventOnDiceThatCannotBeRolled(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('An outcome of the expression cannot be rolled: no die named "nowhere" is declared.');

        new Probabilities()->chance(new AllEqual(), new DiceRoller()->parse('2d{nowhere}'));
    }

    public function testRolledCountsAndSidesStayWithinTheLimits(): void
    {
        $probabilities = new Probabilities(limits: new Limits(dice: 3, sides: 4));

        try {
            $probabilities->distribution(new DiceRoller()->parse('(1d4)d2'));
            self::fail('Four dice go past the limit of three.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertStringContainsString('dice of a roll', $limitExceeded->getMessage());
        }

        $this->expectException(LimitExceeded::class);
        $this->expectExceptionMessageIsOrContains('sides of a die');

        $probabilities->distribution(new DiceRoller()->parse('1d(1d2*3)'));
    }

    public function testTheExpressionStaysWithinTheLimits(): void
    {
        $this->expectException(LimitExceeded::class);

        new Probabilities(limits: new Limits(dice: 3))->distribution(new DiceRoller()->parse('4d6'));
    }

    public function testADistributionIsThatOfOneExpression(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('A distribution is that of one expression; the pool holds 2.');

        new Probabilities()->distribution(new DiceRoller()->parse('1d6, 1d8'));
    }

    public function testANodeIsTakenAlone(): void
    {
        $node = new DiceRoller()->parse('1d4')->expressions[0];

        self::assertSame([1 => '1', 2 => '1', 3 => '1', 4 => '1'], $this->weights(new Probabilities()->distribution($node)));
    }

    #[DataProvider('unsupported')]
    public function testWhatTheComputationDoesNotFollowIsEstimated(string $expression): void
    {
        $distribution = new Probabilities(settings: new Settings(samples: 300, seed: 7))->distribution(new DiceRoller()->parse($expression));

        self::assertTrue($distribution->isEstimated());
        self::assertSame(EstimateReason::Unsupported, $distribution->estimate?->reason);
        self::assertSame(300, $distribution->estimate->samples);
        self::assertSame(7, $distribution->estimate->seed);
        self::assertSame('300', (string) $distribution->total);
        self::assertTrue($distribution->remaining->isZero());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unsupported(): iterable
    {
        yield 'unique dice' => ['3d6u'];
        yield 'dice all the same that explode' => ['2d6t'];
        yield 'occurrences' => ['5d6o2'];
        yield 'groups' => ['5d6g10'];
        yield 'filter of the whole set' => ['4d6f[all:>1]'];
        yield 'the dice before' => ['3d6>=5-@=1'];
        yield 'selection among spread explosions' => ['3d6!kh2'];
        yield 'equal values that contribute differently' => ['4d6min3c[die:3]kh2'];
        yield 'a group' => ['{2d6, 1d12}kh1'];
        yield 'an if' => ['1d20i[>=10]{1}{0}'];
        yield 'a switch' => ['1d6S^[1..3]{1}{2}'];
    }

    public function testARepetitionHasNoDistribution(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('the expression computes with a list, which is not a number.');

        new Probabilities(settings: new Settings(samples: 10))->distribution(new DiceRoller()->parse('repeat(1d6, 2)'));
    }

    public function testADieWhoseFacesAreNotKnownIsEstimated(): void
    {
        $distribution = new Probabilities(settings: new Settings(samples: 50))->distribution(new Pool(new Dice(2, new LoadedDie())));

        self::assertSame([6 => '50'], $this->weights($distribution));
        self::assertSame(EstimateReason::Unsupported, $distribution->estimate?->reason);
    }

    public function testATooLargeComputationIsEstimatedFromItsSeed(): void
    {
        $settings = new Settings(workLimit: 5, samples: 200, seed: 42);
        $estimate = new Probabilities(settings: $settings)->distribution(new DiceRoller()->parse('2d6'));
        $again = new Probabilities(settings: $settings)->distribution(new DiceRoller()->parse('2d6'));
        $other = new Probabilities(settings: new Settings(workLimit: 5, samples: 200, seed: 43))->distribution(new DiceRoller()->parse('2d6'));

        self::assertSame(EstimateReason::TooLarge, $estimate->estimate?->reason);
        self::assertSame('200', (string) $estimate->total);
        self::assertSame($this->weights($estimate), $this->weights($again));
        self::assertNotSame($this->weights($estimate), $this->weights($other));
        self::assertSame(2, $estimate->minimum());
        self::assertSame(12, $estimate->maximum());
        self::assertEqualsWithDelta(7.0, $estimate->mean()->toFloat(), 0.5);
    }

    public function testTheEstimateFollowsTheRounding(): void
    {
        $distribution = new Probabilities(settings: new Settings(workLimit: 1, samples: 100))->distribution(new DiceRoller()->parse('1d6/3'), RoundingMode::PositiveInfinity);

        self::assertSame([1, 2], $distribution->values());
    }

    public function testTheWorkOfAPlainSumIsAFewOperationsPerSum(): void
    {
        // Thirty six-sided dice: thirty windows slid along at most 151 sums.
        $sum = new Probabilities(settings: new Settings(workLimit: 5_000))->distribution(new DiceRoller()->parse('30d6'));

        self::assertFalse($sum->isEstimated());
        self::assertSame('221073919720733357899776', (string) $sum->total);
        self::assertSame('105/1', (string) $sum->mean()->reduced());

        // Squaring a die that is not plain does more work.
        self::assertTrue(new Probabilities(settings: new Settings(workLimit: 5_000))->distribution(new DiceRoller()->parse('30d6ro1'))->isEstimated());
        self::assertFalse(new Probabilities(settings: new Settings(workLimit: 50_000))->distribution(new DiceRoller()->parse('30d6ro1'))->isEstimated());
        self::assertTrue(new Probabilities(settings: new Settings(workLimit: 2_000))->distribution(new DiceRoller()->parse('30d6'))->isEstimated());
    }

    /**
     * Each weight multiplied or added counts one: exactly at its work, a computation is exact; one operation short, it
     * is estimated.
     */
    #[DataProvider('work')]
    public function testTheWorkIsCountedToTheOperation(string $expression, int $work): void
    {
        $shuffled = new CustomDice(new DieDefinition('shuffled', new Face('c', 'C', 3), new Face('a', 'A', 1), new Face('b', 'B', 2)));
        $pool = new DiceRoller(customDice: $shuffled)->parse($expression);

        self::assertFalse(new Probabilities($shuffled, settings: new Settings(workLimit: $work, samples: 1))->distribution($pool)->isEstimated());
        self::assertTrue(new Probabilities($shuffled, settings: new Settings(workLimit: $work - 1, samples: 1))->distribution($pool)->isEstimated());
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function work(): iterable
    {
        // The faces, what each contributes, one window and the final values: 6 each.
        yield 'one die' => ['1d6', 24];
        yield 'plain dice' => ['3d6', 61];
        yield 'custom dice in any order' => ['3d{shuffled}', 15];
        yield 'rerolled' => ['1d6r1', 27];
        yield 'rerolled once and squared' => ['2d6ro1', 76];
        yield 'three rerolled once and squared' => ['3d6ro1', 142];
        yield 'count rolled first' => ['(1d2)d4', 61];
        yield 'selection' => ['4d6kh3', 292];
        yield 'explosion' => ['1d6!', 745];
    }

    public function testTheWorkOfASelectionIsCounted(): void
    {
        self::assertTrue(new Probabilities(settings: new Settings(workLimit: 100, samples: 10))->distribution(new DiceRoller()->parse('4d6kh3'))->isEstimated());
        self::assertFalse(new Probabilities(settings: new Settings(workLimit: 5_000, samples: 10))->distribution(new DiceRoller()->parse('4d6kh3'))->isEstimated());
    }

    public function testTheWorkOfAnExplosionIsCounted(): void
    {
        self::assertTrue(new Probabilities(settings: new Settings(workLimit: 100, samples: 10))->distribution(new DiceRoller()->parse('1d6!'))->isEstimated());
        self::assertTrue(new Probabilities(settings: new Settings(workLimit: 100, samples: 10))->distribution(new DiceRoller()->parse('1d100r1'))->isEstimated());
        self::assertTrue(new Probabilities(settings: new Settings(workLimit: 150, samples: 10))->distribution(new DiceRoller()->parse('1d6+1d4+1d8*2'))->isEstimated());
        self::assertFalse(new Probabilities(settings: new Settings(workLimit: 200, samples: 10))->distribution(new DiceRoller()->parse('1d6+1d4+1d8*2'))->isEstimated());
    }

    /**
     * @return array<int, string>
     */
    private function weights(Distribution $distribution): array
    {
        return array_map(strval(...), $distribution->weights);
    }
}
