<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Critical;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\Look;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\SelectionMode;
use Tilttek\Dice\Expression\Sort;
use Tilttek\Dice\Expression\Transform;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(Comparison::class)]
#[CoversClass(Explosion::class)]
#[CoversClass(Selection::class)]
#[CoversClass(SelectionMode::class)]
#[CoversClass(Operator::class)]
#[CoversClass(Dice::class)]
#[CoversClass(ComputedDice::class)]
#[CoversClass(ValueList::class)]
#[CoversClass(PreviousDice::class)]
#[CoversClass(FunctionCall::class)]
#[CoversClass(MathFunction::class)]
#[CoversClass(Modifiers::class)]
#[CoversClass(Transform::class)]
#[CoversClass(Occurrences::class)]
#[CoversClass(Critical::class)]
#[CoversClass(Look::class)]
#[CoversClass(Pool::class)]
#[CoversClass(StandardDie::class)]
#[CoversClass(FudgeDie::class)]
#[CoversClass(RangeDie::class)]
#[CoversClass(CustomDie::class)]
final class ExpressionTest extends TestCase
{
    /**
     * @return iterable<string, array{Comparator, int, list<bool>}>
     */
    public static function comparisons(): iterable
    {
        yield 'equal' => [Comparator::Equal, 3, [false, true, false]];
        yield 'greater' => [Comparator::Greater, 3, [false, false, true]];
        yield 'greater or equal' => [Comparator::GreaterOrEqual, 3, [false, true, true]];
        yield 'less' => [Comparator::Less, 3, [true, false, false]];
        yield 'less or equal' => [Comparator::LessOrEqual, 3, [true, true, false]];
    }

    /**
     * @param list<bool> $matches for the faces 2, 3 and 4
     */
    #[DataProvider('comparisons')]
    public function testAComparisonMatchesInThePlainSenseOfItsSign(Comparator $comparator, int $value, array $matches): void
    {
        $comparison = new Comparison($comparator, $value);

        self::assertSame($matches, [$comparison->matches(2), $comparison->matches(3), $comparison->matches(4)]);
    }

    /**
     * @return iterable<string, array{Comparator, int, int, int, bool}>
     */
    public static function everyFace(): iterable
    {
        yield 'equal, one face' => [Comparator::Equal, 6, 6, 6, true];
        yield 'equal, a lower face' => [Comparator::Equal, 6, 5, 6, false];
        yield 'equal, a higher face' => [Comparator::Equal, 6, 6, 7, false];
        yield 'greater, all' => [Comparator::Greater, 0, 1, 6, true];
        yield 'greater, not the lowest' => [Comparator::Greater, 1, 1, 6, false];
        yield 'greater or equal, all' => [Comparator::GreaterOrEqual, 1, 1, 6, true];
        yield 'greater or equal, not the lowest' => [Comparator::GreaterOrEqual, 2, 1, 6, false];
        yield 'less, all' => [Comparator::Less, 7, 1, 6, true];
        yield 'less, not the highest' => [Comparator::Less, 6, 1, 6, false];
        yield 'less or equal, all' => [Comparator::LessOrEqual, 6, 1, 6, true];
        yield 'less or equal, not the highest' => [Comparator::LessOrEqual, 5, 1, 6, false];
    }

    #[DataProvider('everyFace')]
    public function testAComparisonKnowsWhetherEveryFaceMatches(Comparator $comparator, int $value, int $lowest, int $highest, bool $all): void
    {
        self::assertSame($all, new Comparison($comparator, $value)->matchesAll($lowest, $highest));
    }

    public function testAnExplosionTriggersOnTheHighestFaceOrItsValidator(): void
    {
        self::assertSame([true, false], [new Explosion()->triggers(6, 6), new Explosion()->triggers(5, 6)]);
        self::assertSame([true, false], [new Explosion(new Comparison(Comparator::GreaterOrEqual, 5))->triggers(5, 6), new Explosion(new Comparison(Comparator::GreaterOrEqual, 5))->triggers(4, 6)]);
        self::assertSame([true, false, false], [new Explosion()->always(1, 1), new Explosion()->always(1, 6), new Explosion(new Comparison(Comparator::Greater, 1))->always(1, 6)]);
        self::assertTrue(new Explosion(new Comparison(Comparator::Greater, 0))->always(1, 6));
        self::assertSame([null, false, false, null], [new Explosion()->on, new Explosion()->compound, new Explosion()->penetrating, new Explosion()->limit]);
        self::assertEquals(new Constant(1), new Explosion(limit: new Constant(1))->limit);
    }

    public function testAnExplosionIsLimitedToOneDieAtLeast(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('An explosion is limited to at least one per die; 0 is not.'));
        new Explosion(limit: new Constant(0));
    }

    public function testSelectionModesKnowWhatTheyKeepAndWhichEnd(): void
    {
        self::assertSame(
            [[true, true], [true, false], [false, true], [false, false]],
            array_map(static fn (SelectionMode $mode): array => [$mode->keeps(), $mode->highest()], [SelectionMode::KeepHighest, SelectionMode::KeepLowest, SelectionMode::DropHighest, SelectionMode::DropLowest]),
        );
    }

    public function testOperatorsBindAsArithmeticDoes(): void
    {
        self::assertSame([1, 1, 2, 2, 2, 4], array_map(static fn (Operator $operator): int => $operator->precedence(), Operator::cases()));
    }

    public function testASelectionKeepsOrDropsAtLeastOneDie(): void
    {
        self::assertSame(1, new Selection(SelectionMode::KeepHighest, 1)->count);

        $this->expectExceptionObject(InvalidDefinition::because('A selection keeps or drops at least one die.'));
        new Selection(SelectionMode::KeepHighest, 0);
    }

    public function testDiceKeepUpToAllAndDropUpToAllButOne(): void
    {
        self::assertSame(3, new Dice(3, new StandardDie(6), new Modifiers(selection: new Selection(SelectionMode::KeepLowest, 3)))->modifiers->selection?->count);
        self::assertSame(2, new Dice(3, new StandardDie(6), new Modifiers(selection: new Selection(SelectionMode::DropHighest, 2)))->modifiers->selection?->count);

        $this->expectExceptionObject(InvalidDefinition::because('3 dice cannot keep 3, nor drop all of them.'));
        new Dice(3, new StandardDie(6), new Modifiers(selection: new Selection(SelectionMode::DropHighest, 3)));
    }

    public function testDiceCannotKeepMoreThanTheyRoll(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('3 dice cannot keep 4, nor drop all of them.'));
        new Dice(3, new StandardDie(6), new Modifiers(selection: new Selection(SelectionMode::KeepHighest, 4)));
    }

    public function testAnExplosionThatEveryFaceTriggersIsRefusedUnlessLimited(): void
    {
        self::assertNotNull(new Dice(4, new FudgeDie(), new Modifiers(explosion: new Explosion()))->modifiers->explosion);
        self::assertNotNull(new Dice(1, new StandardDie(6), new Modifiers(explosion: new Explosion(new Comparison(Comparator::Greater, 1))))->modifiers->explosion);
        self::assertNotNull(new Dice(1, new StandardDie(1), new Modifiers(explosion: new Explosion(limit: new Constant(2))))->modifiers->explosion);

        $this->expectExceptionObject(InvalidDefinition::because('Every face would explode: the roll would never end.'));
        new Dice(1, new StandardDie(1), new Modifiers(explosion: new Explosion()));
    }

    public function testAnExplosionByComparisonOnEveryFaceIsRefused(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('Every face would explode: the roll would never end.'));
        new Dice(1, new StandardDie(6), new Modifiers(explosion: new Explosion(new Comparison(Comparator::Greater, 0))));
    }

    public function testARepeatedRerollOnEveryFaceIsRefused(): void
    {
        self::assertNotNull(new Dice(1, new StandardDie(6), new Modifiers(reroll: new Reroll(new Comparison(Comparator::Less, 7), Repetition::Once)))->modifiers->reroll);
        self::assertNotNull(new Dice(1, new StandardDie(6), new Modifiers(reroll: new Reroll(new Comparison(Comparator::Less, 6))))->modifiers->reroll);

        $this->expectExceptionObject(InvalidDefinition::because('Every face would be rerolled: the roll would never end.'));
        new Dice(1, new StandardDie(6), new Modifiers(reroll: new Reroll(new Comparison(Comparator::Less, 7))));
    }

    public function testRepeatedUniqueDiceNeedAsManyFacesAsDice(): void
    {
        self::assertSame(Repetition::Repeated, new Dice(6, new StandardDie(6), new Modifiers(unique: Repetition::Repeated))->modifiers->unique);
        self::assertSame(Repetition::Once, new Dice(7, new StandardDie(6), new Modifiers(unique: Repetition::Once))->modifiers->unique);
        self::assertSame(Repetition::Repeated, new Dice(3, new RangeDie(-1, 1), new Modifiers(unique: Repetition::Repeated))->modifiers->unique);

        $this->expectExceptionObject(InvalidDefinition::because('4 dice cannot all differ with 3 faces.'));
        new Dice(4, new RangeDie(-1, 1), new Modifiers(unique: Repetition::Repeated));
    }

    public function testDiceAllTheSameExplodeFromTwoDice(): void
    {
        self::assertTrue(new Dice(2, new StandardDie(6), new Modifiers(explodeAllSame: true))->modifiers->explodeAllSame);

        $this->expectExceptionObject(InvalidDefinition::because('Dice all the same explode from two dice: one die is always the same as itself.'));
        new Dice(1, new StandardDie(6), new Modifiers(explodeAllSame: true));
    }

    public function testDiceRollAtLeastOneDie(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('An expression rolls at least one die of each kind it names.'));
        new Dice(0, new StandardDie(6));
    }

    /**
     * @return iterable<string, array{Modifiers}>
     */
    public static function everyModifier(): iterable
    {
        $on = new Comparison(Comparator::Equal, 1);

        yield 'reroll' => [new Modifiers(reroll: new Reroll($on))];
        yield 'unique' => [new Modifiers(unique: Repetition::Once)];
        yield 'explosion' => [new Modifiers(explosion: new Explosion())];
        yield 'dice all the same' => [new Modifiers(explodeAllSame: true)];
        yield 'floor' => [new Modifiers(minimum: 2)];
        yield 'ceiling' => [new Modifiers(maximum: 2)];
        yield 'transformation' => [new Modifiers(transforms: [new Transform(new Condition($on), 2)])];
        yield 'filter' => [new Modifiers(filter: new Condition($on))];
        yield 'selection' => [new Modifiers(selection: new Selection(SelectionMode::KeepHighest, 1))];
        yield 'successes' => [new Modifiers(success: new Condition($on))];
        yield 'failures' => [new Modifiers(success: new Condition($on), failure: $on)];
        yield 'occurrences' => [new Modifiers(occurrences: new Occurrences())];
        yield 'groups' => [new Modifiers(groups: 10)];
        yield 'critical success' => [new Modifiers(criticalSuccess: new Critical())];
        yield 'critical failure' => [new Modifiers(criticalFailure: new Critical())];
        yield 'look' => [new Modifiers(looks: [new Look('accent')])];
        yield 'sort' => [new Modifiers(sort: Sort::Descending)];
    }

    #[DataProvider('everyModifier')]
    public function testACustomDieTakesNoModifier(Modifiers $modifiers): void
    {
        self::assertFalse($modifiers->isEmpty());
        self::assertTrue(new Modifiers()->isEmpty());
        self::assertSame(Modifiers::class, new Dice(2, new CustomDie('chromatic'))->modifiers::class);

        $this->expectExceptionObject(InvalidDefinition::because('A custom die takes no modifier.'));
        new Dice(2, new CustomDie('chromatic'), $modifiers);
    }

    #[DataProvider('everyModifier')]
    public function testACustomDieRolledByACountTakesNoModifier(Modifiers $modifiers): void
    {
        self::assertSame(Modifiers::class, new ComputedDice(new Constant(1), new CustomDie('chromatic'))->modifiers::class);
        self::assertSame($modifiers, new ComputedDice(new Constant(1), new StandardDie(6), $modifiers)->modifiers);

        $this->expectExceptionObject(InvalidDefinition::because('A custom die takes no modifier.'));
        new ComputedDice(new Constant(1), new CustomDie('chromatic'), $modifiers);
    }

    public function testModifiersKnowWhetherTheyRoll(): void
    {
        $on = new Comparison(Comparator::Equal, 1);

        self::assertSame(
            [true, true, true, true, false, false],
            array_map(static fn (Modifiers $modifiers): bool => $modifiers->rolls(), [new Modifiers(reroll: new Reroll($on)), new Modifiers(unique: Repetition::Repeated), new Modifiers(explosion: new Explosion()), new Modifiers(explodeAllSame: true), new Modifiers(minimum: 1), new Modifiers()]),
        );
    }

    /**
     * @return iterable<string, array{callable(): Modifiers, string}>
     */
    public static function invalidModifiers(): iterable
    {
        $on = new Comparison(Comparator::Equal, 1);

        yield 'failures without successes' => [static fn (): Modifiers => new Modifiers(failure: $on), 'Failures take a success away: they need successes judged die by die.'];
        yield 'failures of all the dice' => [static fn (): Modifiers => new Modifiers(success: new Condition($on, Scope::All), failure: $on), 'Failures take a success away: they need successes judged die by die.'];
        yield 'failures by a value unlike' => [static fn (): Modifiers => new Modifiers(success: new Condition($on), failure: new Comparison(Comparator::NotEqual, 1)), 'Failures are judged by a plain comparison, not by "!=".'];
        yield 'successes and occurrences' => [static fn (): Modifiers => new Modifiers(success: new Condition($on), occurrences: new Occurrences()), 'A set of dice counts successes, occurrences or groups: one of them at most.'];
        yield 'successes and groups' => [static fn (): Modifiers => new Modifiers(success: new Condition($on), groups: 1), 'A set of dice counts successes, occurrences or groups: one of them at most.'];
        yield 'occurrences and groups' => [static fn (): Modifiers => new Modifiers(occurrences: new Occurrences(), groups: 1), 'A set of dice counts successes, occurrences or groups: one of them at most.'];
        yield 'a floor above the ceiling' => [static fn (): Modifiers => new Modifiers(minimum: 3, maximum: 2), 'The floor of a die, 3, is above its ceiling, 2.'];
        yield 'groups of nothing' => [static fn (): Modifiers => new Modifiers(groups: 0), 'Groups reach a sum of at least 1; 0 is not.'];
        yield 'a look token for every die first' => [static fn (): Modifiers => new Modifiers(looks: [new Look('a'), new Look('b', 1)]), 'Only the last look token goes to every die left; the others give their count.'];
    }

    /**
     * @param callable(): Modifiers $modifiers
     */
    #[DataProvider('invalidModifiers')]
    public function testModifiersThatContradictEachOtherAreRefused(callable $modifiers, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));
        $modifiers();
    }

    public function testModifiersThatAgreeAreKept(): void
    {
        $on = new Comparison(Comparator::Equal, 1);

        self::assertSame(2, new Modifiers(minimum: 2, maximum: 2)->maximum);
        self::assertSame(1, new Modifiers(groups: 1)->groups);
        self::assertSame($on, new Modifiers(success: new Condition($on, Scope::Die), failure: $on)->failure);
        self::assertCount(3, new Modifiers(looks: [new Look('a', 1), new Look('b', 2), new Look('c')])->looks);
    }

    public function testATransformationJudgesEachDie(): void
    {
        self::assertSame(3, new Transform(new Condition(new Comparison(Comparator::Equal, 1), Scope::Die), 3)->value);

        $this->expectExceptionObject(InvalidDefinition::because('A transformation judges each die; the scope "any" judges them together.'));
        new Transform(new Condition(new Comparison(Comparator::Equal, 1), Scope::Any), 3);
    }

    public function testOccurrencesHoldOneDieAtLeast(): void
    {
        self::assertSame([2, null, 1], [new Occurrences()->width, new Occurrences()->height, new Occurrences(1)->width]);

        $this->expectExceptionObject(InvalidDefinition::because('A set of occurrences holds at least one die; 0 is not.'));
        new Occurrences(0);
    }

    public function testACriticalMarkFallsOnItsValidatorOrTheBound(): void
    {
        self::assertSame([true, false], [new Critical()->marks(20, 20), new Critical()->marks(19, 20)]);
        self::assertSame([true, false], [new Critical(new Comparison(Comparator::GreaterOrEqual, 19))->marks(19, 20), new Critical(new Comparison(Comparator::GreaterOrEqual, 19))->marks(18, 18)]);
    }

    public function testALookTokenIsANameNeverAColour(): void
    {
        self::assertSame(['accent-2', 3], [new Look('accent-2', 3)->token, new Look('accent-2', 3)->count]);
        self::assertSame(str_repeat('a', 32), new Look(str_repeat('a', 32))->token);

        foreach (['#ff0000', 'Accent', '2nd', '', str_repeat('a', 33), "a\n"] as $token) {
            try {
                new Look($token);
                self::fail(\sprintf('The token "%s" was taken.', $token));
            } catch (InvalidDefinition $invalidDefinition) {
                self::assertSame(\sprintf('A look token is 1 to 32 lower-case letters, digits or "-", starting with a letter; "%s" is not.', $token), $invalidDefinition->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidDefinition::because('A look token is given to at least one die; 0 is not.'));
        new Look('a', 0);
    }

    public function testAListOfValuesIsNotRolled(): void
    {
        $list = new ValueList([3, -1, 8], new Modifiers(selection: new Selection(SelectionMode::KeepHighest, 3)));

        self::assertSame([3, -1, 8], $list->values);

        try {
            new ValueList([1, 2], new Modifiers(explodeAllSame: true));
            self::fail('A list exploded.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame('A list of values is not rolled: it takes no reroll and no explosion.', $invalidDefinition->getMessage());
        }

        try {
            new ValueList([1, 2], new Modifiers(selection: new Selection(SelectionMode::DropLowest, 2)));
            self::fail('A list dropped all its values.');
        } catch (InvalidDefinition $invalidDefinition) {
            self::assertSame('2 dice cannot keep 2, nor drop all of them.', $invalidDefinition->getMessage());
        }

        $this->expectExceptionObject(InvalidDefinition::because('A list of values holds at least one value.'));
        new ValueList([]);
    }

    public function testTheDiceBeforeAreNotRolledAgain(): void
    {
        self::assertTrue(new PreviousDice()->modifiers->isEmpty());

        $this->expectExceptionObject(InvalidDefinition::because('The dice of the set before are not rolled again: "@" takes no reroll and no explosion.'));
        new PreviousDice(new Modifiers(unique: Repetition::Once));
    }

    /**
     * @return iterable<string, array{MathFunction, int, ?string}>
     */
    public static function functions(): iterable
    {
        yield 'floor of two' => [MathFunction::Floor, 2, 'floor() takes one argument; 2 given.'];
        yield 'ceil of two' => [MathFunction::Ceil, 2, 'ceil() takes one argument; 2 given.'];
        yield 'abs of one' => [MathFunction::Abs, 1, null];
        yield 'min of one' => [MathFunction::Min, 1, 'min() takes two arguments or more; 1 given.'];
        yield 'max of two' => [MathFunction::Max, 2, null];
        yield 'max of three' => [MathFunction::Max, 3, null];
    }

    #[DataProvider('functions')]
    public function testAFunctionTakesItsArguments(MathFunction $function, int $count, ?string $refusal): void
    {
        $arguments = array_fill(0, $count, new Constant(1));

        if (null !== $refusal) {
            $this->expectExceptionObject(InvalidDefinition::because($refusal));
        }

        self::assertCount($count, new FunctionCall($function, ...$arguments)->arguments);
    }

    public function testFunctionsKnowHowTheyRound(): void
    {
        self::assertSame(
            [RoundingMode::NegativeInfinity, RoundingMode::PositiveInfinity, RoundingMode::HalfAwayFromZero, null, null, null, null],
            array_map(static fn (MathFunction $function): ?RoundingMode => $function->rounding(), MathFunction::cases()),
        );
        self::assertSame([false, false, false, false, true, true, false], array_map(static fn (MathFunction $function): bool => $function->isVariadic(), MathFunction::cases()));
        self::assertSame([1, 1, 1, 1, 2, 2, 2], array_map(static fn (MathFunction $function): int => $function->arity(), MathFunction::cases()));
        self::assertSame([0, 1], array_keys(new FunctionCall(MathFunction::Max, new Constant(1), ...['x' => new Constant(2)])->arguments));
    }

    public function testDiceKnowTheirFaces(): void
    {
        self::assertSame([1, 1, 1], [new StandardDie(1)->sides, new StandardDie(1)->lowest(), new StandardDie(1)->highest()]);
        self::assertSame([1, 20], [new StandardDie(20)->lowest(), new StandardDie(20)->highest()]);
        self::assertSame([-1, 1, 2, 1], [new FudgeDie()->lowest(), new FudgeDie()->highest(), new FudgeDie()->variant, new FudgeDie(1)->variant]);
        self::assertSame([-1, 1, 3, 3], [new RangeDie(-1, 1)->lowest(), new RangeDie(-1, 1)->highest(), new RangeDie(3, 3)->from, new RangeDie(3, 3)->to]);
        self::assertSame('a', new CustomDie('a')->name);
    }

    public function testDiceRollTheirFaces(): void
    {
        self::assertSame([1, 6], [new StandardDie(6)->roll(ScriptedEngine::randomizer(0)), new StandardDie(6)->roll(ScriptedEngine::randomizer(5))]);
        self::assertSame([-1, 0, 1], [new FudgeDie()->roll(ScriptedEngine::randomizer(0)), new FudgeDie()->roll(ScriptedEngine::randomizer(1)), new FudgeDie()->roll(ScriptedEngine::randomizer(2))]);
        self::assertSame([-1, 0, 0, 0, 0, 1], array_map(static fn (int $offset): int => new FudgeDie(1)->roll(ScriptedEngine::randomizer($offset)), range(0, 5)));
        self::assertSame([-3, 2], [new RangeDie(-3, 2)->roll(ScriptedEngine::randomizer(0)), new RangeDie(-3, 2)->roll(ScriptedEngine::randomizer(5))]);
    }

    public function testFudgeDiceDrawFromTheirOwnRange(): void
    {
        $standard = new Randomizer(new Mt19937(5));
        $weighted = new Randomizer(new Mt19937(5));
        $expected = new Randomizer(new Mt19937(5));
        $draws = [];

        for ($index = 0; $index < 30; ++$index) {
            $draws[] = [new FudgeDie()->roll($standard), $expected->getInt(-1, 1)];
        }

        self::assertSame(array_column($draws, 1), array_column($draws, 0));

        $expected = new Randomizer(new Mt19937(5));
        $faces = [];

        for ($index = 0; $index < 30; ++$index) {
            $faces[] = [new FudgeDie(1)->roll($weighted), match ($expected->getInt(1, 6)) {
                1 => -1,
                6 => 1,
                default => 0,
            }];
        }

        self::assertSame(array_column($faces, 1), array_column($faces, 0));
    }

    public function testAFudgeDieIsOfOneOrTwo(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A Fudge die is dF.1 or dF.2; dF.3 is not.'));
        new FudgeDie(3);
    }

    public function testAFudgeDieIsOfOneOrTwoNotNone(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A Fudge die is dF.1 or dF.2; dF.0 is not.'));
        new FudgeDie(0);
    }

    public function testARangeDieGoesUp(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A die goes from its lowest face to its highest; 2..1 does not.'));
        new RangeDie(2, 1);
    }

    public function testADieHasAtLeastOneSide(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A die has at least one side; 0 is not a number of sides.'));
        new StandardDie(0);
    }

    public function testACustomDieHasAValidName(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A die name is 1 to 32 lower-case letters, digits, "_" or "-", starting with a letter; "1a" is not.'));
        new CustomDie('1a');
    }

    public function testAPoolListsItsExpressions(): void
    {
        $pool = new Pool(new Constant(1), new Constant(2), new Constant(3));

        self::assertSame([1, 2, 3], array_map(static fn (Node $node): int => $node instanceof Constant ? $node->value : 0, $pool->expressions));
        self::assertSame('1, 2, 3', $pool->canonical());
        self::assertSame([0, 1], array_keys(new Pool(new Constant(1), ...['x' => new Constant(2)])->expressions));
    }
}
