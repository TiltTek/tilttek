<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Placeholder;
use Tilttek\Dice\Expression\PlaceholderKind;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\Reference;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Result\BranchResult;
use Tilttek\Dice\Result\ConstantResult;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\ExpressionResult;
use Tilttek\Dice\Result\ListResult;
use Tilttek\Dice\Result\Outputs;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Result\ReferenceResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\Dice\Result\TextResult;
use Tilttek\Dice\Rolling\Context;
use Tilttek\Dice\Rolling\Grouping;
use Tilttek\Dice\Rolling\Roller;
use Tilttek\Dice\Rolling\SetRoller;
use Tilttek\Dice\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(Roller::class)]
#[CoversClass(Context::class)]
#[CoversClass(BranchResult::class)]
#[CoversClass(ListResult::class)]
#[CoversClass(TextResult::class)]
#[CoversClass(ReferenceResult::class)]
#[CoversClass(Outputs::class)]
#[CoversClass(ExpressionResult::class)]
#[CoversClass(PoolResult::class)]
#[CoversClass(DiceResult::class)]
#[CoversClass(CannotRoll::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Grouping::class)]
#[CoversClass(SetRoller::class)]
final class LanguageRollTest extends TestCase
{
    public function testInstructionsReferToTheResultsBeforeThem(): void
    {
        $result = $this->roll('3d6; $1+1; $1[0]; $1[2]*10', 0, 5, 2);

        self::assertSame([10, 11, 1, 30], $result->totals());
        self::assertSame(['type' => 'reference', 'instruction' => 1, 'index' => 0, 'output' => 1], $result->results[2]->result->toArray());
        self::assertSame([], $result->results[1]->result->sets());
        self::assertSame([], $result->results[2]->result->sets());
        self::assertSame([['a', 'b'], 'b', ['a', 'b']], $this->roll('2L[a,b]; $1[1]; $1', 0, 1)->outputs());
        self::assertSame(['ab', 'b', 'ab'], $this->roll('2Ln[a,b]; $1[1]; $1', 0, 1)->outputs());
        self::assertSame([['a', 'b'], 'b'], $this->roll('1S[1]{2L[a,b]}; $1[1]', 0, 1)->outputs());
        self::assertSame(['ab', 'b'], $this->roll('1S^[1]{2Ln[a,b]}; $1[1]', 0, 1)->outputs());
        self::assertSame([['Red', 'Blue'], 'Blue'], $this->roll('2d{colours}; $1[1]', 0, 1)->outputs());
    }

    public function testAnItemThatIsNotThereCannotBeRolled(): void
    {
        $this->expectExceptionObject(CannotRoll::noItem(1, 3, 3));

        $this->roll('3d6; $1[3]', 0, 0, 0);
    }

    public function testATextReferredToTakesNoPartInArithmetic(): void
    {
        $this->expectExceptionObject(CannotRoll::notANumber('a text'));

        $this->roll('"a"; $1+1');
    }

    public function testAListReferredToTakesNoPartInArithmetic(): void
    {
        $this->expectExceptionObject(CannotRoll::notANumber('a list'));

        $this->roll('L[a]; $1+1', 0);
    }

    public function testAnInstructionAfterCannotBeReferredTo(): void
    {
        $this->expectExceptionObject(CannotRoll::noInstruction(2));

        new Roller()->roll(new Pool(new Reference(2), new Constant(1)), ScriptedEngine::randomizer());
    }

    public function testTheDiceOfInstructionsAreJudgedAgain(): void
    {
        $result = $this->roll('8d10; @1c[>=6]; @1c[=1]; b[2,3]kh2', 0, 5, 9, 0, 6, 2, 3, 7);

        self::assertSame([40, 4, 2, 20], $result->totals());
        self::assertSame([null, null, null, null], array_map(static fn (ExpressionResult $instruction): ?int => $instruction->mergedInto, $result->results));
        self::assertCount(16, $result->results[3]->result->sets()[0]->dice);
        self::assertSame([1, 2], array_map(static fn (RolledDie $die): int => $die->value, $this->roll('1d6; 2d8kh1; 2d4kl1; b[1,3]', 0, 1, 2, 1, 3)->results[3]->result->sets()[0]->dice));
        self::assertSame([0, []], [$this->roll('3; @1')->totals()[1], $this->roll('3; @1kh1')->results[1]->result->sets()[0]->dice]);
        self::assertSame([3, 6], $this->roll('1d6; @1+@', 2)->totals());
    }

    public function testMergedInstructionsGiveTheirDiceAway(): void
    {
        $result = $this->roll('1d6; 1d8; 1d4; m[1,2]kh1', 4, 1, 3);

        self::assertSame([null, null, 4, 5], $result->totals());
        self::assertSame([4, 4, null], [$result->results[0]->mergedInto, $result->results[1]->mergedInto, $result->results[2]->mergedInto]);
        self::assertSame([5, 2], [$result->results[0]->output(), $result->results[1]->output()]);
        self::assertSame([4, 4, null, null], array_column($result->toArray(), 'merged_into'));
        self::assertSame([null, 5], $this->roll('1d6; m[1]', 4)->totals());
    }

    public function testTheDiceOfInstructionsKeepTheirMarksFromTheirOwnValues(): void
    {
        $set = $this->roll('3d6; @1cscf', 0, 3, 5)->results[1]->result->sets()[0];

        self::assertSame(['critical-failure', null, 'critical-success'], array_map(static fn (RolledDie $die): ?string => $die->critical?->value, $set->dice));
    }

    public function testFacesWithoutValueStayFaces(): void
    {
        $result = $this->roll('2d{colours}; @1; $1[1]', 0, 1);

        self::assertSame([['Red', 'Blue'], ['Red', 'Blue'], 'Blue'], $result->outputs());
        self::assertSame([null, null, null], $result->totals());
        self::assertFalse($result->results[1]->result->sets()[0]->numeric);
    }

    public function testAGroupJudgesTheTotalsOfItsExpressions(): void
    {
        $result = $this->roll('{2d6, 1d10+1}kh1cs; @1kh1', 0, 1, 9);
        $group = $result->results[0]->result;

        self::assertSame([11, 11], $result->totals());
        self::assertInstanceOf(DiceResult::class, $group);
        self::assertSame(['expression-1', 'expression-2'], array_keys($group->parts));
        self::assertSame([[3, false, null], [11, true, 'critical-success']], array_map(static fn (RolledDie $die): array => [$die->value, $die->kept, $die->critical?->value], $group->dice));
        self::assertCount(3, $group->sets());
        self::assertSame([2], $this->roll('{1d6, 1d6, 1d6}>=4', 3, 5, 0)->totals());
        self::assertSame([1], $this->roll('{1d6+1d6}=7', 0, 5)->totals());
        self::assertSame([4], $this->roll('{1d6+@}kh1', 1)->totals());
        self::assertSame([12], $this->roll('{1d6, 1d6}kh1+@', 5, 0)->totals());
    }

    public function testAnExpressionOfAGroupSeesNoneOfTheOuterDice(): void
    {
        $this->expectExceptionObject(CannotRoll::nothingBefore());

        new Roller()->roll(new Pool(new Operation(Operator::Add, new Dice(1, new StandardDie(6)), new Group([new PreviousDice()]))), ScriptedEngine::randomizer(0));
    }

    public function testATextListDrawsByWeight(): void
    {
        $result = $this->roll('3L[a[2],b,c[3]]', 0, 2, 5);
        $list = $result->results[0]->result;

        self::assertInstanceOf(ListResult::class, $list);
        self::assertSame([['a', 'b', 'c'], [0, 1, 2], [1, 3, 6]], [$list->items, $list->draws, $list->rolls]);
        self::assertSame(['a', 'a', 'b', 'c', 'c', 'c'], array_map(fn (int $offset): int|string => $this->first($this->roll('L[a[2],b,c[3]]', $offset)), range(0, 5)));
    }

    public function testATextListDrawsOnItsRanges(): void
    {
        $list = $this->roll('2L[-1..0:x,1..3:y]', 0, 4)->results[0]->result;

        self::assertInstanceOf(ListResult::class, $list);
        self::assertSame([['x', 'y'], [0, 1], [-1, 3]], [$list->items, $list->draws, $list->rolls]);
        self::assertSame(['x', 'x', 'y', 'y', 'y'], array_map(fn (int $offset): int|string => $this->first($this->roll('L[-1..0:x,1..3:y]', $offset)), range(0, 4)));
    }

    public function testAUniqueTextListDrawsAmongTheItemsLeft(): void
    {
        $list = $this->roll('3Lu[a,b[2],c]', 1, 0, 0)->results[0]->result;
        $ranged = $this->roll('2Lu[1..2:x,3..5:y,6..6:z]', 2, 2)->results[0]->result;

        self::assertInstanceOf(ListResult::class, $list);
        self::assertInstanceOf(ListResult::class, $ranged);
        self::assertSame([['b', 'a', 'c'], [1, 0, 2], [2, 1, 1]], [$list->items, $list->draws, $list->rolls]);
        self::assertSame([['y', 'z'], [1, 2], [3, 6]], [$ranged->items, $ranged->draws, $ranged->rolls]);
        self::assertSame(['kaaa'], $this->roll('3Ln[ka,a]', 0, 1, 1)->outputs());
    }

    public function testATextListCountedByARoll(): void
    {
        $result = $this->roll('(1d4)L[a]', 1, 0, 0);
        $list = $result->results[0]->result;

        self::assertSame([['a', 'a']], $result->outputs());
        self::assertInstanceOf(ListResult::class, $list);
        self::assertSame(2, $list->count?->value());
        self::assertCount(1, $list->sets());
        self::assertNull($this->roll('2L[a]', 0, 0)->results[0]->result instanceof ListResult ? null : 'a list');
    }

    public function testATextListCannotDrawWhatItsRolledCountAsks(): void
    {
        $this->expectExceptionObject(CannotRoll::invalidCount('3 different items cannot be drawn from 2.'));

        $this->roll('(1d4)Lu[a,b]', 2);
    }

    public function testATextListCannotDrawNothing(): void
    {
        $this->expectException(CannotRoll::class);
        $this->expectExceptionMessageIsOrContains('The count rolled cannot be used: a list draws at least one item; 0 is not.');

        $this->roll('(1d4-1)L[a]', 0);
    }

    public function testATextListDrawsWithinTheLimitOfDice(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 2, 3));

        new Roller(new Limits(dice: 2))->roll(new DiceParser()->parse('(1d4)L[a]'), ScriptedEngine::randomizer(1));
    }

    public function testASentenceSpeaksOfTheInstructionsBefore(): void
    {
        $result = $this->roll('2d6; 2L[a,b]; "%1 / %2 / %3 / $1 $1[1] / @1 / $2[0]\n"', 0, 5, 1, 0);

        self::assertSame("b, a /  / 7, b, a / 7 6 / 1, 6 / b\n", $result->outputs()[2]);
        self::assertSame(['type' => 'text', 'text' => "b, a /  / 7, b, a / 7 6 / 1, 6 / b\n"], $result->results[2]->result->toArray());
        self::assertSame([], $result->results[2]->result->sets());
        self::assertSame("b, a /  / 7, b, a / 7 6 / 1, 6 / b\n", $result->results[2]->text());
    }

    public function testASentenceInABranchSpeaksOfItsSubject(): void
    {
        $result = $this->roll('1d4; 2d6i[>5]{"%1 (%2) after %3"}; "%1"', 0, 2, 3);

        self::assertSame([1, '7 (3, 4) after 1', '7 (3, 4) after 1'], $result->outputs());
        self::assertSame([['2', '7']], $this->roll('2d6S[>5]{1d4i[>1]{"%1"}}[>5]{"%1"}', 2, 3, 1)->outputs());
    }

    public function testASentenceGoesNoLongerThanTheLimitOfTexts(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('characters of a text', 5, 6));

        new Roller(new Limits(text: 5))->roll(new DiceParser()->parse('"abc"; "$1$1"'), ScriptedEngine::randomizer());
    }

    public function testATextAsLongAsTheLimitIsGiven(): void
    {
        self::assertSame(['abc', 'abcabc'], new Roller(new Limits(text: 6))->roll(new DiceParser()->parse('"abc"; "$1$1"'), ScriptedEngine::randomizer())->outputs());
        self::assertSame(['ééé'], new Roller(new Limits(text: 3))->roll(new DiceParser()->parse('"ééé"'), ScriptedEngine::randomizer())->outputs());
    }

    public function testASentenceWithNothingToSpeakOfCannotBeRolled(): void
    {
        $this->expectExceptionObject(CannotRoll::noInstruction(1));

        new Roller()->roll(new Pool(new Sentence([new Placeholder(PlaceholderKind::Subject)])), ScriptedEngine::randomizer());
    }

    /**
     * @return iterable<string, array{string, list<int>, int|string|list<int|string>, list<int>, bool}>
     */
    public static function branches(): iterable
    {
        yield 'an if that holds' => ['1d20i[>=15]{"hit"}{"miss"}', [14], 'hit', [0], false];
        yield 'an if that does not hold' => ['1d20i[>=15]{"hit"}{"miss"}', [13], 'miss', [], true];
        yield 'an if without else' => ['1d20i[>=15]{"hit"}', [0], 1, [], false];
        yield 'all the dice' => ['3d6i[all:>3]{1}{0}', [3, 4, 5], 1, [0], false];
        yield 'not all the dice' => ['3d6i[all:>3]{1}{0}', [3, 2, 5], 0, [], true];
        yield 'any die' => ['3d6i[any:6]{1}{0}', [0, 5, 0], 1, [0], false];
        yield 'no die' => ['3d6i[any:6]{1}{0}', [0, 4, 0], 0, [], true];
        yield 'the total of the dice' => ['(2d6+5)i[total:>=7]{1}{0}', [0, 5], 1, [0], false];
        yield 'the total of the dice, without what is added' => ['(2d6+5)i[total:>=8]{1}{0}', [0, 5], 0, [], true];
        yield 'the value, with what is added' => ['(2d6+5)i[>=12]{1}{0}', [0, 5], 1, [0], false];
        yield 'all of a value without dice' => ['5i[all:5]{1}{0}', [], 1, [0], false];
        yield 'the total of a value without dice' => ['5i[total:6]{1}{0}', [], 0, [], true];
        yield 'a switch' => ['1d6S[1..3]{"low"}[3..6]{"high"}[2]{"two"}{"none"}', [2], ['low', 'high'], [0, 1], false];
        yield 'a switch of lists' => ['1d6S[1..3]{2L[a,b]}[3]{2Ln[c,d]}', [2, 0, 1, 1, 0], ['a', 'b', 'dc'], [0, 1], false];
        yield 'an exclusive switch' => ['1d6S^[1..3]{"low"}[3..6]{"high"}{"none"}', [2], 'low', [0], false];
        yield 'an exclusive switch, its second case' => ['1d6S^[1..2]{"low"}[3..6]{"high"}{"none"}', [5], 'high', [1], false];
        yield 'a switch to its default' => ['1d6S[1]{"one"}[2]{"two"}{"other"}', [5], 'other', [], true];
        yield 'a switch to its subject' => ['1d6S[1]{"one"}', [5], 6, [], false];
    }

    /**
     * @param list<int>                   $offsets
     * @param int|string|list<int|string> $output
     * @param list<int>                   $matched
     */
    #[DataProvider('branches')]
    public function testABranchGivesWhatItsConditionSays(string $expression, array $offsets, int|string|array $output, array $matched, bool $default): void
    {
        $branch = $this->roll($expression, ...$offsets)->results[0]->result;

        self::assertInstanceOf(BranchResult::class, $branch);
        self::assertSame([$output, $matched, $default], [$branch->output(), $branch->matched, $branch->default]);
    }

    public function testOnlyTheBranchThatHoldsIsRolled(): void
    {
        $result = $this->roll('1d6i[6]{1d20}{1d4}; 1d6S^[1]{1d8}[6]{1d10}[6]{1d12}', 0, 3, 5, 9);
        $if = $result->results[0]->result;

        self::assertSame([4, 10], $result->totals());
        self::assertInstanceOf(BranchResult::class, $if);
        self::assertSame(['type' => 'branch', 'matched' => [], 'default' => true], \array_slice($if->toArray(), 0, 3));
        self::assertCount(2, $if->sets());
        self::assertSame(4, $if->value());
        self::assertNull($this->roll('1d6i[6]{1}', 0)->results[0]->result->toArray()['result']);
        self::assertSame(['type', 'matched', 'default', 'subject', 'result'], array_keys($if->toArray()));

        $sets = $this->roll('(1d4+1d6)i[>0]{1d8+1d10}', 0, 0, 0, 0)->results[0]->result->sets();
        self::assertSame(['1d4', '1d6', '1d8', '1d10'], array_map(static fn (DiceResult $set): string => $set->toArray()['notation'], $sets));
        self::assertCount(1, $this->roll('1d6i[6]{1}', 0)->results[0]->result->sets());
    }

    public function testABranchOnATextCannotBeRolled(): void
    {
        $this->expectExceptionObject(CannotRoll::notANumber('a text'));

        $this->roll('"a"; $1i[1]{1}');
    }

    public function testRepeatRollsItsExpressionIntoAList(): void
    {
        $result = $this->roll('repeat(1d6, 3); repeat("x", 2); repeat(2L[a,b], 2); repeat(1d6, 1d4)', 0, 1, 2, 0, 1, 1, 0, 1, 4, 5);
        $rolled = $result->results[3]->result;

        self::assertSame([[1, 2, 3], ['x', 'x'], ['a', 'b', 'b', 'a'], [5, 6]], $result->outputs());
        self::assertSame([null, null, null, null], $result->totals());
        self::assertInstanceOf(ListResult::class, $rolled);
        self::assertSame([2, 3], [$rolled->count?->value(), \count($rolled->sets())]);
        self::assertSame(['type' => 'list', 'items' => [1, 2, 3], 'joined' => false, 'draws' => [], 'rolls' => []], \array_slice($result->results[0]->result->toArray(), 0, 5));
        self::assertNull($result->results[0]->result->toArray()['count']);
        self::assertSame(['type', 'items', 'joined', 'draws', 'rolls', 'count', 'parts'], array_keys($rolled->toArray()));
        self::assertSame('dice', $rolled->toArray()['count']['type'] ?? null);

        $sets = $this->roll('repeat(1d4+1d6, 2)', 0, 0, 0, 0)->results[0]->result->sets();
        self::assertSame(['1d4', '1d6', '1d4', '1d6'], array_map(static fn (DiceResult $set): string => $set->toArray()['notation'], $sets));
        self::assertCount(3, $result->results[0]->result instanceof ListResult ? $result->results[0]->result->parts : []);
    }

    public function testRepeatRollsAtLeastOnce(): void
    {
        $this->expectExceptionObject(CannotRoll::invalidCount('repeat() rolls at least once; 0 is not.'));

        $this->roll('repeat(1, 1d4-1)', 0);
    }

    public function testRepetitionsRolledAreCountedAgainstTheirLimit(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('repetitions of a roll', 3, 4));

        new Roller(new Limits(repetitions: 3))->roll(new DiceParser()->parse('repeat(1, 2); repeat(1, 1d4)'), ScriptedEngine::randomizer(1));
    }

    public function testFunctionsOtherThanRoundingKeepTheRoundingOfTheRoll(): void
    {
        self::assertSame([[4, 4], 4, 4, 3, 3], $this->rollWith('repeat(7/2, 2); abs(7/2); max(7/2, 1); floor(7/2); min(round(5/2), 3)', RoundingMode::PositiveInfinity)->outputs());
        self::assertSame([[3, 3], -3], $this->rollWith('repeat(7/2, 2); abs(-7/2)-7', RoundingMode::NegativeInfinity)->outputs());
    }

    public function testResultsThatAreNotNumbersRefuseToGiveOne(): void
    {
        foreach ([new TextResult('a'), new ListResult(['a']), new ReferenceResult(1, null, 'a'), new ReferenceResult(1, null, ['a'])] as $index => $result) {
            try {
                $result->value();
                self::fail('A text or a list gave a number.');
            } catch (CannotRoll $cannotRoll) {
                self::assertSame(\sprintf('The expression computes with %s, which is not a number.', 0 === $index % 2 ? 'a text' : 'a list'), $cannotRoll->getMessage());
            }
        }

        self::assertSame([3, 3, []], [new ReferenceResult(1, null, 3)->value(), new BranchResult(new ConstantResult(1), [0], new ConstantResult(3))->value(), new ReferenceResult(1, null, 3)->sets()]);
        self::assertSame(['a', 'b'], new ListResult(['a', 'b'])->output());
        self::assertSame('ab', new ListResult(['a', 'b'], true)->output());
    }

    public function testOutputsAreWrittenAndFlattened(): void
    {
        self::assertSame(['3', 'a', 'a, 3', ''], [Outputs::text(3), Outputs::text('a'), Outputs::text(['a', 3]), Outputs::text([])]);
        self::assertSame([[3], ['a'], ['a', 3]], [Outputs::items(3), Outputs::items('a'), Outputs::items(['a', 3])]);
    }

    public function testFacesWithoutValueAreRefusedWhereANumberIsComputed(): void
    {
        foreach (['{1d{colours}}', '1d{colours}i[1]{1}', '1d{colours}S[1]{1}', '(1d{colours})L[a]', 'repeat(1, 1d{colours})', '1i[1]{1d{colours}+1}', '1i[2]{1}{1d{colours}+1}', '1S[1]{1d{colours}+1}', '1S[2]{1}[1]{1d{colours}+1}', '1S[2]{1}{1d{colours}+1}', 'repeat(1d{colours}+1, 1)'] as $expression) {
            try {
                $this->roll($expression, 0);
                self::fail(\sprintf('%s was rolled.', $expression));
            } catch (CannotRoll $cannotRoll) {
                self::assertSame(CannotRoll::notNumeric('colours')->getMessage(), $cannotRoll->getMessage());
            }
        }

        self::assertSame([['Red'], ['Blue'], ['Red']], $this->roll('1i[1]{1d{colours}}; 1S^[1]{1d{colours}}{1}; repeat(1d{colours}, 1)', 0, 1, 0)->outputs());
        self::assertSame([['Red']], $this->roll('1i[2]{1}{1d{colours}}', 0)->outputs());
        self::assertSame([['Red']], $this->roll('1S[2]{1}{1d{colours}}', 0)->outputs());
    }

    public function testTheWholeLanguageIsVerifiableAndReplayable(): void
    {
        $dice = new DiceRoller(customDice: $this->customDice());
        $pool = $dice->parse('8d10; @1c[>=6]; m[1]kh2; {2d6, 1d12}kh1; 3Lu[a,b[2],c]; repeat(1d4, 2); 1d20i[>=10]{"hit $2"}{"miss"}; 1d6S[1..3]{"low"}[4..6]{"high"}; "%3"');
        $seed = Seed::fromHex(str_repeat('ab', 32));
        $roll = $dice->rollVerifiable($pool, $seed, 'player', 3, RoundingMode::HalfEven);

        self::assertTrue($dice->fairRoller()->verify($seed->commitment(), $seed, 'player', 3, $dice->parse($roll->toArray()['expression']), $roll->result->toArray(), RoundingMode::HalfEven));
        self::assertFalse($dice->fairRoller()->verify($seed->commitment(), $seed, 'other', 3, $pool, $roll->result->toArray(), RoundingMode::HalfEven));

        $replayable = $dice->rollReplayable($pool, new Randomizer(new Xoshiro256StarStar(5)));
        $replayed = $dice->replay($replayable->id, $dice->parse($replayable->expression()), $replayable->seed);

        self::assertTrue($replayed->result->equals($replayable->result));
        self::assertCount(9, $replayed->result->outputs());
    }

    public function testTheMostGroupsAreFoundQuicklyForTwelveDice(): void
    {
        $start = hrtime(true);
        $this->roll('12d10g10', ...array_fill(0, 12, 4));

        self::assertLessThan(2_000_000_000, hrtime(true) - $start);
    }

    /**
     * The first item of the list the first instruction gave.
     */
    private function first(PoolResult $result): int|string
    {
        $output = $result->outputs()[0];

        return \is_array($output) ? $output[0] : $output;
    }

    private function roll(string $expression, int ...$offsets): PoolResult
    {
        return $this->rollWith($expression, RoundingMode::NegativeInfinity, ...$offsets);
    }

    private function rollWith(string $expression, RoundingMode $rounding, int ...$offsets): PoolResult
    {
        return new Roller(customDice: $this->customDice())->roll(new DiceParser()->parse($expression), ScriptedEngine::randomizer(...$offsets), $rounding);
    }

    private function customDice(): CustomDice
    {
        return new CustomDice(new DieDefinition('colours', new Face('red', 'Red', color: '#ff0000', shape: Shape::Circle), new Face('blue', 'Blue', color: '#0000ff', shape: Shape::Square)));
    }
}
