<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Result\ConstantResult;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\DieOrigin;
use Tilttek\Dice\Result\ExpressionResult;
use Tilttek\Dice\Result\NegationResult;
use Tilttek\Dice\Result\OperationResult;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\Dice\Rolling\Roller;
use Tilttek\Dice\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(Roller::class)]
#[CoversClass(PoolResult::class)]
#[CoversClass(ExpressionResult::class)]
#[CoversClass(DiceResult::class)]
#[CoversClass(ConstantResult::class)]
#[CoversClass(OperationResult::class)]
#[CoversClass(NegationResult::class)]
#[CoversClass(RolledDie::class)]
#[CoversClass(CannotRoll::class)]
final class RollerTest extends TestCase
{
    public function testARollKeepsEveryDieAndItsTotal(): void
    {
        $result = $this->roll('3d6+2, -1d4', 0, 5, 2, 3);

        self::assertSame([12, -4], $result->totals());
        self::assertSame([
            ['expression' => '3d6+2', 'total' => 12, 'output' => 12, 'merged_into' => null, 'detail' => ['type' => 'operation', 'operator' => '+', 'value' => 12, 'left' => ['type' => 'dice', 'notation' => '3d6', 'value' => 10, 'numeric' => true, 'dice' => [
                ['value' => 1, 'face' => null, 'origin' => 'initial', 'kept' => true, 'rerolled' => false, 'exploded' => false, 'success' => null, 'failure' => false, 'critical' => null, 'look' => null, 'rolls' => [1]],
                ['value' => 6, 'face' => null, 'origin' => 'initial', 'kept' => true, 'rerolled' => false, 'exploded' => false, 'success' => null, 'failure' => false, 'critical' => null, 'look' => null, 'rolls' => [6]],
                ['value' => 3, 'face' => null, 'origin' => 'initial', 'kept' => true, 'rerolled' => false, 'exploded' => false, 'success' => null, 'failure' => false, 'critical' => null, 'look' => null, 'rolls' => [3]],
            ], 'groups' => [], 'parts' => [], 'notices' => []], 'right' => ['type' => 'constant', 'value' => 2]]],
            ['expression' => '-1d4', 'total' => -4, 'output' => -4, 'merged_into' => null, 'detail' => ['type' => 'negation', 'value' => -4, 'operand' => ['type' => 'dice', 'notation' => '1d4', 'value' => 4, 'numeric' => true, 'dice' => [
                ['value' => 4, 'face' => null, 'origin' => 'initial', 'kept' => true, 'rerolled' => false, 'exploded' => false, 'success' => null, 'failure' => false, 'critical' => null, 'look' => null, 'rolls' => [4]],
            ], 'groups' => [], 'parts' => [], 'notices' => []]]],
        ], $result->toArray());
    }

    public function testAMatchingDieIsRerolledOnce(): void
    {
        self::assertSame([[1, 'initial', false, true], [4, 'reroll', true, false], [5, 'initial', true, false]], $this->dice($this->roll('2d6ro1', 0, 3, 4)));
        self::assertSame([[1, 'initial', false, true], [1, 'reroll', true, false]], $this->dice($this->roll('1d6ro1', 0, 0)));
        self::assertSame([9, 1], $this->roll('2d6ro1, 1d6ro1', 0, 3, 4, 0, 0)->totals());
    }

    public function testAMatchingDieIsRerolledUntilItNoLongerMatches(): void
    {
        self::assertSame([[1, 'initial', false, true], [2, 'reroll', false, true], [1, 'reroll', false, true], [3, 'reroll', true, false]], $this->dice($this->roll('1d6r<3', 0, 1, 0, 2)));
        self::assertSame([[4, 'initial', true, false]], $this->dice($this->roll('1d6r<3', 3)));
    }

    public function testADieExplodesUntilItStops(): void
    {
        $result = $this->roll('2d6!', 5, 5, 1, 2);

        self::assertSame([17], $result->totals());
        self::assertSame([[6, 'initial', true, false, true], [6, 'explosion', true, false, true], [2, 'explosion', true, false, false], [3, 'initial', true, false, false]], $this->dice($result, true));
        self::assertSame([12], $this->roll('1d6!>=5', 4, 5, 0)->totals());
    }

    public function testARerolledDieMayExplode(): void
    {
        self::assertSame([[1, 'initial', false, true, false], [6, 'reroll', true, false, true], [1, 'explosion', true, false, false]], $this->dice($this->roll('1d6ro1!', 0, 5, 0), true));
    }

    public function testTheExplosionsOfAPoolAreLimited(): void
    {
        $roller = new Roller(new Limits(explosions: 2));
        $parser = new DiceParser();

        self::assertSame([13], $roller->roll($parser->parse('1d6!'), ScriptedEngine::randomizer(5, 5, 0))->totals());

        $this->expectExceptionObject(LimitExceeded::of('explosions of a roll', 2, 3));
        $roller->roll($parser->parse('1d6!, 1d6!'), ScriptedEngine::randomizer(5, 0, 5, 5, 0));
    }

    /**
     * @return iterable<string, array{string, list<int>, list<bool>, int}>
     */
    public static function selections(): iterable
    {
        yield 'keep the highest' => ['4d6kh3', [0, 5, 2, 5], [false, true, true, true], 15];
        yield 'keep the first of equal highest' => ['3d6kh1', [5, 5, 0], [true, false, false], 6];
        yield 'keep the first of equal lowest' => ['3d6kl1', [3, 0, 0], [false, true, false], 1];
        yield 'drop the first of equal highest' => ['3d6dh1', [5, 5, 0], [false, true, true], 7];
        yield 'drop the first of equal lowest' => ['3d6dl1', [0, 0, 5], [false, true, true], 7];
        yield 'keep the lowest two' => ['4d6kl2', [5, 0, 3, 1], [false, true, false, true], 3];
    }

    /**
     * @param list<int>  $offsets
     * @param list<bool> $kept
     */
    #[DataProvider('selections')]
    public function testASelectionKeepsOrDropsTheHighestOrTheLowest(string $expression, array $offsets, array $kept, int $total): void
    {
        $result = $this->roll($expression, ...$offsets);

        self::assertSame([$total], $result->totals());
        self::assertSame($kept, array_map(static fn (array $die): bool => true === $die[2], $this->dice($result)));
    }

    public function testASelectionLeavesRerolledDiceOut(): void
    {
        $result = $this->roll('2d6ro1kh1', 0, 1, 4);

        self::assertSame([[1, 'initial', false, true], [2, 'reroll', false, false], [5, 'initial', true, false]], $this->dice($result));
        self::assertSame([5], $result->totals());
        self::assertSame([3], self::roll('2d6ro6kh1', 5, 0, 2)->totals());
    }

    public function testSuccessesAreCountedAmongTheKeptDice(): void
    {
        $result = $this->roll('4d10>=7', 6, 9, 0, 5);

        self::assertSame([2], $result->totals());
        self::assertSame([true, true, false, false], array_map(static fn (RolledDie $die): ?bool => $die->success, $this->detail($result)->dice));

        $selected = $this->roll('3d10kh2>=7', 6, 0, 9);
        self::assertSame([2], $selected->totals());
        self::assertSame([true, null, true], array_map(static fn (RolledDie $die): ?bool => $die->success, $this->detail($selected)->dice));
        self::assertSame([7, 10], array_map(static fn (RolledDie $die): int => $die->value, $this->detail($selected)->kept()));
    }

    public function testFudgeDiceFallFromMinusOneToOne(): void
    {
        self::assertSame([1], $this->roll('4dF', 0, 1, 2, 2)->totals());
    }

    public function testCustomDiceAreTalliedBySymbolAndSummedWhenTheyCount(): void
    {
        $result = $this->roll('4d{colours}, 2d{coin}+1', 1, 0, 1, 2, 0, 1);
        $colours = $this->detail($result);

        self::assertSame([null, 2], $result->totals());
        self::assertSame(['blue' => 2, 'red' => 1, 'star' => 1], $colours->tally());
        self::assertSame(['blue', 'red', 'blue', 'star'], array_map(static fn (RolledDie $die): ?string => $die->face?->id, $colours->dice));
        self::assertSame([false, 0], [$colours->numeric, $colours->value()]);
        self::assertSame(['value' => 0, 'face' => 'blue', 'origin' => 'initial', 'kept' => true, 'rerolled' => false, 'exploded' => false, 'success' => null, 'failure' => false, 'critical' => null, 'look' => null, 'rolls' => [0]], $colours->dice[0]->toArray());
        self::assertSame([], $this->detail($this->roll('1d6', 0))->tally());
        self::assertSame(['red', 'heads', 1], [$this->detail($this->roll('1d{colours}', 3))->dice[0]->face?->id, $this->detail($this->roll('1d{coin}', 2))->dice[0]->face?->id, $this->detail($this->roll('1d{coin}', 2))->dice[0]->value]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function facesInArithmetic(): iterable
    {
        yield 'left' => ['1d{colours}+1'];
        yield 'right' => ['1+1d{colours}'];
        yield 'negated' => ['-1d{colours}'];
        yield 'in the second expression' => ['1d6, 2*1d{colours}'];
    }

    #[DataProvider('facesInArithmetic')]
    public function testFacesWithoutValueStayOutOfArithmetic(string $expression): void
    {
        $this->expectExceptionObject(CannotRoll::notNumeric('colours'));
        $this->roll($expression);
    }

    public function testAnUnknownDieIsRefusedBeforeAnyDieIsRolled(): void
    {
        $this->expectExceptionObject(new CannotRoll('No die named "bone" is declared.'));
        $this->roll('1d6, 1d{bone}');
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function arithmetic(): iterable
    {
        yield 'addition' => ['2+3', 5];
        yield 'subtraction' => ['1-2', -1];
        yield 'multiplication' => ['2*3-1', 5];
        yield 'exact division' => ['6/3', 2];
        yield 'exact negative division' => ['-6/3', -2];
        yield 'division' => ['7/2', 3];
        yield 'negative dividend' => ['-7/2', -4];
        yield 'negative divisor' => ['7/-2', -4];
        yield 'both negative' => ['-7/-2', 3];
        yield 'smaller dividend' => ['1/3', 0];
        yield 'negative smaller dividend' => ['-1/3', -1];
    }

    #[DataProvider('arithmetic')]
    public function testArithmeticIsIntegerAndDivisionRoundsDown(string $expression, int $total): void
    {
        self::assertSame([$total], $this->roll($expression)->totals());
    }

    public function testADivisionByZeroIsRefused(): void
    {
        $this->expectExceptionObject(new CannotRoll('The expression divides by zero.'));
        $this->roll('1/(1-1)');
    }

    /**
     * @return iterable<string, array{Node}>
     */
    public static function overflows(): iterable
    {
        yield 'addition' => [new Operation(Operator::Add, new Constant(\PHP_INT_MAX), new Constant(1))];
        yield 'subtraction' => [new Operation(Operator::Subtract, new Constant(\PHP_INT_MIN), new Constant(1))];
        yield 'multiplication' => [new Operation(Operator::Multiply, new Constant(\PHP_INT_MAX), new Constant(2))];
        yield 'division' => [new Operation(Operator::Divide, new Constant(\PHP_INT_MIN), new Constant(-1))];
        yield 'negation' => [new Negation(new Constant(\PHP_INT_MIN))];
        yield 'custom faces' => [new Dice(2, new CustomDie('huge'))];
    }

    #[DataProvider('overflows')]
    public function testAResultTooLargeIsRefused(Node $node): void
    {
        $roller = new Roller(customDice: new CustomDice(new DieDefinition('huge', new Face('a', 'A', \PHP_INT_MAX), new Face('b', 'B', \PHP_INT_MAX))));

        $this->expectExceptionObject(new CannotRoll('The result is too large for an integer.'));
        $roller->roll(new Pool($node), ScriptedEngine::randomizer(0, 0));
    }

    public function testThePoolIsCheckedAgainstTheLimits(): void
    {
        try {
            new Roller(new Limits(sides: 6))->roll(new Pool(new Dice(1, new StandardDie(7))), ScriptedEngine::randomizer(0));
            self::fail('The sides passed.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The sides of a die are limited to 6; this asks for 7.', $limitExceeded->getMessage());
        }

        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 2, 3));
        new Roller(new Limits(dice: 2))->roll(new Pool(new Dice(3, new StandardDie(6))), ScriptedEngine::randomizer());
    }

    public function testANodeOrADieOfAnotherLibraryIsRefused(): void
    {
        $node = new class implements Node {};

        try {
            new Roller()->roll(new Pool($node), ScriptedEngine::randomizer());
            self::fail('The node was rolled.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame(\sprintf('%s is not a node the library rolls.', $node::class), $invalidDefinition->getMessage());
        }

        $die = new class implements DieType {};

        $this->expectExceptionObject(InvalidDefinition::because(\sprintf('%s is not a die the library rolls.', $die::class)));
        new Roller()->roll(new Pool(new Dice(1, $die)), ScriptedEngine::randomizer());
    }

    public function testTheSameSeedGivesTheSameRoll(): void
    {
        $pool = new DiceParser()->parse('10d6!kh5, 4dF, 6d10>=7');
        $roller = new Roller();
        $first = $roller->roll($pool, new Randomizer(new Mt19937(1234)));

        self::assertTrue($first->equals($roller->roll($pool, new Randomizer(new Mt19937(1234)))));
        self::assertFalse($first->equals($roller->roll($pool, new Randomizer(new Mt19937(4321)))));
        self::assertSame($pool, $first->pool);
        self::assertSame($pool->expressions[1], $first->results[1]->expression);
    }

    public function testResultsKeepWhatTheyWereBuiltFrom(): void
    {
        $left = new ConstantResult(2);
        $right = new ConstantResult(3);
        $operation = new OperationResult(Operator::Multiply, $left, $right, 6);
        $negation = new NegationResult($operation, -6);

        self::assertSame([$left, $right, Operator::Multiply, 6, $operation, -6], [$operation->left, $operation->right, $operation->operator, $operation->value(), $negation->operand, $negation->value()]);

        $die = new RolledDie(4, DieOrigin::Explosion, false, true, true, true);
        self::assertSame([4, DieOrigin::Explosion, false, true, true, true, null], [$die->value, $die->origin, $die->kept, $die->rerolled, $die->exploded, $die->success, $die->face]);
        self::assertSame([4, DieOrigin::Explosion, false, true, true, false], [$die->judged(false)->value, $die->judged(false)->origin, $die->judged(false)->kept, $die->judged(false)->rerolled, $die->judged(false)->exploded, $die->judged(false)->success]);
        $face = new Face('star', 'Star', shape: Shape::Star);
        $kept = new RolledDie(1, DieOrigin::Reroll, true, false, true, false, $face)->dropped();
        self::assertSame([1, DieOrigin::Reroll, false, false, true, false, $face], [$kept->value, $kept->origin, $kept->kept, $kept->rerolled, $kept->exploded, $kept->success, $kept->face]);
        self::assertSame($face, new RolledDie(1, face: $face)->judged(true)->face);
    }

    private function roll(string $expression, int ...$offsets): PoolResult
    {
        $custom = new CustomDice(
            new DieDefinition('colours', new Face('red', 'Red', color: '#ff0000', shape: Shape::Circle), new Face('blue', 'Blue', color: '#0000ff', shape: Shape::Square), new Face('star', 'Star', shape: Shape::Star)),
            new DieDefinition('coin', new Face('heads', 'Heads', 1), new Face('tails', 'Tails', 0)),
        );

        return new Roller(customDice: $custom)->roll(new DiceParser()->parse($expression), ScriptedEngine::randomizer(...$offsets));
    }

    private function detail(PoolResult $result): DiceResult
    {
        $detail = $result->results[0]->result;
        self::assertInstanceOf(DiceResult::class, $detail);

        return $detail;
    }

    /**
     * @return list<list<bool|int|string>>
     */
    private function dice(PoolResult $result, bool $explosions = false): array
    {
        return array_map(
            static fn (RolledDie $die): array => $explosions ? [$die->value, $die->origin->value, $die->kept, $die->rerolled, $die->exploded] : [$die->value, $die->origin->value, $die->kept, $die->rerolled],
            $this->detail($result)->dice,
        );
    }
}
