<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Parser\DiceParser;
use Tilttek\Dice\Result\ConstantResult;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\FunctionResult;
use Tilttek\Dice\Result\Mark;
use Tilttek\Dice\Result\Notice;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\Dice\Rolling\Context;
use Tilttek\Dice\Rolling\Grouping;
use Tilttek\Dice\Rolling\Roller;
use Tilttek\Dice\Rolling\SetRoller;
use Tilttek\Dice\Tests\Unit\Fixture\ScriptedEngine;

#[CoversClass(Roller::class)]
#[CoversClass(SetRoller::class)]
#[CoversClass(Grouping::class)]
#[CoversClass(Context::class)]
#[CoversClass(DiceResult::class)]
#[CoversClass(FunctionResult::class)]
#[CoversClass(RolledDie::class)]
#[CoversClass(Mark::class)]
#[CoversClass(CannotRoll::class)]
final class ModifierRollTest extends TestCase
{
    public function testADieOnARangeFallsBetweenItsBounds(): void
    {
        self::assertSame([[-1, 1], 0], $this->values('2d[-1..1]', 0, 2));
    }

    public function testWeightedFudgeDiceAreMostlyBlank(): void
    {
        self::assertSame([[-1, 0, 0, 1], 0], $this->values('4dF.1', 0, 1, 4, 5));
    }

    public function testACountRolledFirstGivesTheDice(): void
    {
        $result = $this->roll('(1d4)d6', 1, 0, 5);
        $set = $this->set($result);

        self::assertSame([7], $result->totals());
        self::assertSame([1, 6], array_map(static fn (RolledDie $die): int => $die->value, $set->dice));
        self::assertSame(['count'], array_keys($set->parts));
        self::assertSame(2, $set->parts['count']->value());
        self::assertSame('2d6', $set->toArray()['notation']);
        self::assertSame(['type' => 'dice', 'notation' => '1d4', 'value' => 2], \array_slice($set->toArray()['parts']['count'], 0, 3));
        self::assertSame('(1d4)d6', $result->toArray()[0]['expression']);
        self::assertCount(2, $set->sets());
        self::assertSame($set, $set->sets()[1]);
    }

    public function testTheSetsOfEveryPartAreListed(): void
    {
        self::assertSame([2, 4, 5], array_map(static fn (DiceResult $set): int => $set->value(), $this->set($this->roll('(1d4)d(1d6)', 1, 3, 0, 3))->sets()));
    }

    public function testListsAndRolledCountsShareTheLimitOfDice(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 6, 8));
        new Roller(new Limits(dice: 6))->roll(new DiceParser()->parse('[1,2,3]+(1d4)d6'), ScriptedEngine::randomizer(3, 0, 0, 0, 0));
    }

    public function testSidesRolledFirstGiveTheDie(): void
    {
        $set = $this->set($this->roll('1d(1d4+2)', 1, 3));

        self::assertSame([4, 4], [$set->value(), $set->parts['sides']->value()]);
        self::assertSame(['count', 'sides'], array_keys($set->parts));
        self::assertInstanceOf(Dice::class, $set->expression);
        self::assertEquals(new StandardDie(4), $set->expression->die);
        self::assertSame([2, 4], array_map(static fn (DiceResult $part): int => $part->value(), $set->sets()));
    }

    public function testACountRolledFirstMayBeAnyDie(): void
    {
        self::assertSame([[1, 0], 1], $this->values('(1d2)dF', 1, 2, 1));
        self::assertSame([['heads', 'tails'], [1]], [array_map(static fn (RolledDie $die): ?string => $die->face?->id, $this->set($this->roll('(1d2)d{coin}', 1, 0, 1))->dice), $this->roll('(1d2)d{coin}', 1, 0, 1)->totals()]);
    }

    public function testRolledDiceThatCannotBeRolledAreRefused(): void
    {
        $this->expectExceptionObject(new CannotRoll('The dice cannot be rolled once their count and sides are known: an expression rolls at least one die of each kind it names.'));
        $this->roll('(1d4-1)d6', 0);
    }

    public function testRolledSidesBeyondTheLimitAreRefused(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('sides of a die', 6, 7));
        new Roller(new Limits(sides: 6))->roll(new DiceParser()->parse('1d(1d4+5)'), ScriptedEngine::randomizer(1));
    }

    public function testRolledCountsAreCountedAgainstTheLimitOfDice(): void
    {
        $roller = new Roller(new Limits(dice: 5));

        self::assertSame([4], $roller->roll(new DiceParser()->parse('(1d4)d6'), ScriptedEngine::randomizer(3, 0, 0, 0, 0))->totals());

        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 5, 6));
        $roller->roll(new DiceParser()->parse('(1d4)d6, 1d6'), ScriptedEngine::randomizer(3, 0, 0, 0, 0, 0));
    }

    public function testAListOfValuesIsJudgedAsDice(): void
    {
        $set = $this->set($this->roll('[10,25,43,8]kh1'));

        self::assertSame([43, [false, false, true, false]], [$set->value(), array_map(static fn (RolledDie $die): bool => $die->kept, $set->dice)]);
        self::assertSame([], $set->parts);
        self::assertSame('[10,25,43,8]kh1', $set->toArray()['notation']);
    }

    public function testAListOfValuesCountsAgainstTheLimitOfDice(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 2, 3));
        new Roller(new Limits(dice: 2))->roll(new Pool(new ValueList([1, 2, 3])), ScriptedEngine::randomizer());
    }

    public function testAFloorAndACeilingBoundEachDie(): void
    {
        self::assertSame([[3, 1], 4], $this->values('2d6max3', 5, 0));
        self::assertSame([[2, 9], 11], $this->values('2d6min2max5t[5:9]', 0, 5));

        $set = $this->set($this->roll('3d6min3max5', 0, 3, 5));

        self::assertSame([12, [3, 4, 5]], [$set->value(), array_map(static fn (RolledDie $die): int => $die->value, $set->dice)]);
        self::assertSame([[1], [4], [6]], array_map(static fn (RolledDie $die): array => $die->rolls, $set->dice));
        self::assertSame([1, 4, 6], array_map(static fn (RolledDie $die): int => $die->natural(), $set->dice));
    }

    public function testACompoundExplosionAddsToTheSameDie(): void
    {
        $set = $this->set($this->roll('1d6!!', 5, 5, 1));

        self::assertCount(1, $set->dice);
        self::assertSame([14, [6, 6, 2], true, 'initial'], [$set->dice[0]->value, $set->dice[0]->rolls, $set->dice[0]->exploded, $set->dice[0]->origin->value]);
        self::assertSame([[3], false], [$this->set($this->roll('1d6!!', 2))->dice[0]->rolls, $this->set($this->roll('1d6!!', 2))->dice[0]->exploded]);
    }

    public function testAPenetratingExplosionTakesOneFromEachDieItAdds(): void
    {
        $set = $this->set($this->roll('1d6!p', 5, 5, 1));

        self::assertSame([[6, 'initial', true], [5, 'explosion', true], [1, 'explosion', false]], array_map(static fn (RolledDie $die): array => [$die->value, $die->origin->value, $die->exploded], $set->dice));
        self::assertSame(12, $set->value());
        self::assertSame([[6, 5, 1], 12], [$this->set($this->roll('1d6!!p', 5, 5, 1))->dice[0]->rolls, $this->set($this->roll('1d6!!p', 5, 5, 1))->value()]);
    }

    public function testAnExplosionStopsAtItsLimit(): void
    {
        $set = $this->set($this->roll('1d6!(2)', 5, 5, 5));

        self::assertSame([[6, true], [6, true], [6, false]], array_map(static fn (RolledDie $die): array => [$die->value, $die->exploded], $set->dice));
        self::assertEquals(['explosion-limit' => new ConstantResult(2)], $set->parts);
        self::assertSame([[6, 6], 12], [array_map(static fn (RolledDie $die): int => $die->value, $this->set($this->roll('1d6!(1d4)', 0, 5, 5))->dice), $this->roll('1d6!(1d4)', 0, 5, 5)->totals()[0]]);
        self::assertSame([6], $this->roll('1d6!(1d4-4)', 0, 5)->totals());
        self::assertSame([[[6, 6], [6, 2]], 20], [array_map(static fn (RolledDie $die): array => $die->rolls, $this->set($this->roll('2d6!!(1)', 5, 5, 5, 1))->dice), $this->roll('2d6!!(1)', 5, 5, 5, 1)->totals()[0]]);
    }

    public function testARerollAndAddAddsOnceToTheSameDie(): void
    {
        self::assertSame([[10, 10], 20], [$this->set($this->roll('1d10a10', 9, 9))->dice[0]->rolls, $this->roll('1d10a10', 9, 9)->totals()[0]]);
    }

    public function testExplosionsAreCountedAgainstTheirLimit(): void
    {
        $roller = new Roller(new Limits(explosions: 2));

        self::assertSame([13], $roller->roll(new DiceParser()->parse('1d6!!'), ScriptedEngine::randomizer(5, 5, 0))->totals());

        $this->expectExceptionObject(LimitExceeded::of('explosions of a roll', 2, 3));
        $roller->roll(new DiceParser()->parse('1d6!!, 1d6!p'), ScriptedEngine::randomizer(5, 5, 0, 5, 0));
    }

    public function testASelectionChoosesAmongTheDiceThatCount(): void
    {
        self::assertSame([[false, false, true], 5], $this->kept('2d6ro1kh1', 0, 1, 4));
        self::assertSame([[false, true, false], 1], $this->kept('3d6kl1', 3, 0, 0));
        self::assertSame([[false, false, true], 3], $this->kept('2d6ro6kh1', 5, 0, 2));
        self::assertSame([[true, false, true], 7], $this->kept('3d6dh1', 3, 5, 2));
    }

    public function testRerollsAreCountedAgainstTheirLimit(): void
    {
        $roller = new Roller(new Limits(rerolls: 2));

        self::assertSame([2], $roller->roll(new DiceParser()->parse('1d6r1'), ScriptedEngine::randomizer(0, 0, 1))->totals());

        $this->expectExceptionObject(LimitExceeded::of('rerolls of a roll', 2, 3));
        $roller->roll(new DiceParser()->parse('1d6r1, 1d6ro1'), ScriptedEngine::randomizer(0, 0, 1, 0, 0));
    }

    public function testUniqueDiceRerollAValueAlreadyShown(): void
    {
        $set = $this->set($this->roll('3d6u', 0, 0, 1, 1, 2));

        self::assertSame([[1, true], [1, false], [2, true], [2, false], [3, true]], array_map(static fn (RolledDie $die): array => [$die->value, $die->counts()], $set->dice));
        self::assertSame(6, $set->value());
        self::assertSame([[1, 1, 1], 2], [array_map(static fn (RolledDie $die): int => $die->value, $this->set($this->roll('2d6uo', 0, 0, 0))->dice), $this->roll('2d6uo', 0, 0, 0)->totals()[0]]);
    }

    public function testUniqueDiceIgnoreTheDiceRerolled(): void
    {
        self::assertSame([[6, 3, 6, 6], 9], [array_map(static fn (RolledDie $die): int => $die->value, $this->set($this->roll('2d6ro6u', 5, 2, 5, 5))->dice), $this->roll('2d6ro6u', 5, 2, 5, 5)->totals()[0]]);
    }

    public function testUniqueRerollsAreCountedAgainstTheirLimit(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('rerolls of a roll', 1, 2));
        new Roller(new Limits(rerolls: 1))->roll(new DiceParser()->parse('2d6u'), ScriptedEngine::randomizer(0, 0, 0));
    }

    public function testDiceAllTheSameExplodeTogether(): void
    {
        $set = $this->set($this->roll('2d6t', 2, 2, 4, 4, 0, 3));

        self::assertSame(21, $set->value());
        self::assertSame([[3, 'initial', true], [3, 'initial', true], [5, 'explosion', true], [5, 'explosion', true], [1, 'explosion', false], [4, 'explosion', false]], array_map(static fn (RolledDie $die): array => [$die->value, $die->origin->value, $die->exploded], $set->dice));
        self::assertSame([[1, false], [3, true], [3, true], [5, false], [6, false]], array_map(static fn (RolledDie $die): array => [$die->value, $die->exploded], $this->set($this->roll('2d6ro1t', 0, 2, 2, 4, 5))->dice));
    }

    public function testDiceAllTheSameCountTheirExplosions(): void
    {
        $this->expectExceptionObject(LimitExceeded::of('explosions of a roll', 1, 2));
        new Roller(new Limits(explosions: 1))->roll(new DiceParser()->parse('2d6t'), ScriptedEngine::randomizer(2, 2, 4, 4));
    }

    public function testTheFirstTransformationThatMatchesApplies(): void
    {
        self::assertSame([[10, 0, 3], 13], $this->values('3d6t[>4:10,1:0,1..6:3]', 5, 0, 2));
        self::assertSame([[0, 3], 3], $this->values('2d6min3t[die:1:0]', 0, 1));
        self::assertSame([[0, 0], 0], $this->values('2d6min3t[3:0]', 0, 1));
    }

    public function testAFilterDropsTheDiceThatDoNotMatch(): void
    {
        self::assertSame([[true, false, true, false], 4], $this->kept('4d6f[!=6]', 0, 5, 2, 5));
        self::assertSame([[false, true], 6], $this->kept('2d6min5f[die:>4]', 0, 5));
        self::assertSame([[true, true], 7], $this->kept('2d6f[all:>2]', 2, 3));
        self::assertSame([[false, false], 0], $this->kept('2d6f[all:>2]', 2, 0));
        self::assertSame([[true, true], 7], $this->kept('2d6f[any:6]', 5, 0));
        self::assertSame([[false, false], 0], $this->kept('2d6f[any:6]', 1, 0));
        self::assertSame([[true, true], 11], $this->kept('2d6f[total:>=10]', 4, 5));
        self::assertSame([[false, false], 0], $this->kept('2d6f[total:>=10]', 0, 0));
        self::assertSame([[false, true, true], 12], $this->kept('2d6ro1f[all:6]', 0, 5, 5));
    }

    public function testDiceAreListedInTheOrderAsked(): void
    {
        self::assertSame([[1, 3, 6], 10], $this->values('3d6sa', 5, 0, 2));
        self::assertSame([[6, 3, 1], 10], $this->values('3d6sd', 5, 0, 2));
        self::assertSame([[6, 6, 1, 1], ['initial', 'explosion', 'explosion', 'initial']], [$this->values('2d6!sd', 5, 5, 0, 0)[0], array_map(static fn (RolledDie $die): string => $die->origin->value, $this->set($this->roll('2d6!sd', 5, 5, 0, 0))->dice)]);
    }

    public function testFailuresTakeSuccessesAway(): void
    {
        $set = $this->set($this->roll('4d10>=7f1', 6, 0, 9, 1));

        self::assertSame(1, $set->value());
        self::assertSame([[true, false], [false, true], [true, false], [false, false]], array_map(static fn (RolledDie $die): array => [$die->success, $die->failure], $set->dice));
        self::assertSame(-2, $this->set($this->roll('2d10>=7f<3', 0, 1))->value());
        self::assertSame([1, [true, false]], [$this->set($this->roll('1d10>=1f1', 0))->value(), [$this->set($this->roll('1d10>=1f1', 0))->dice[0]->success, $this->set($this->roll('1d10>=1f1', 0))->dice[0]->failure]]);
        self::assertSame([[false, null], -1], [array_map(static fn (RolledDie $die): ?bool => $die->success, $this->set($this->roll('2d10kh1>=7f1', 0, 0))->dice), $this->roll('2d10kh1>=7f1', 0, 0)->totals()[0]]);
    }

    public function testSuccessesTakenTogetherCountOneOrNone(): void
    {
        self::assertSame([1, 0], [$this->roll('3d6c[all:>1]', 1, 2, 3)->totals()[0], $this->roll('3d6c[all:>1]', 1, 0, 3)->totals()[0]]);
        self::assertSame([1, 0], [$this->roll('3d6c[any:6]', 1, 5, 3)->totals()[0], $this->roll('3d6c[any:6]', 1, 0, 3)->totals()[0]]);
        self::assertSame([1, 0], [$this->roll('3d6c[total:>=10]', 5, 2, 0)->totals()[0], $this->roll('3d6c[total:>=10]', 1, 0, 3)->totals()[0]]);
        self::assertSame([null, null], array_map(static fn (RolledDie $die): ?bool => $die->success, $this->set($this->roll('2d6c[all:1]', 0, 0))->dice));
        self::assertSame(1, $this->roll('2d6kh1c[all:6]', 5, 0)->totals()[0]);
    }

    public function testSuccessesJudgeTheDieAsItFellOrItsFinalValue(): void
    {
        self::assertSame([1, 2], [$this->roll('2d6min4c[die:4]', 3, 0)->totals()[0], $this->roll('2d6min4c[4]', 3, 0)->totals()[0]]);
    }

    public function testTheDiceBeforeAreJudgedAgain(): void
    {
        $result = $this->roll('4d10>=7-@=1', 6, 0, 9, 0);

        self::assertSame([0], $result->totals());
        self::assertSame([2, 2], array_map(static fn (DiceResult $set): int => $set->value(), $result->results[0]->result->sets()));
        self::assertSame([18], $this->roll('3d6kh2+@', 0, 5, 2)->totals());
        self::assertSame([[true, false], [true, true], [true, false], [true, true]], array_map(static fn (RolledDie $die): array => [$die->kept, $die->success], $result->results[0]->result->sets()[1]->dice));
        self::assertSame('@=1', $result->results[0]->result->sets()[1]->toArray()['notation']);
        self::assertSame([[2, 3], 3], $this->values('[1,2,3]kh2*0+@kh1'));
        self::assertSame([[null, false, null], [null, false, null]], array_map(static fn (RolledDie $die): array => [$die->success, $die->failure, $die->critical], $this->roll('2d10>=7f1cs+@', 9, 0)->results[0]->result->sets()[1]->dice));
    }

    public function testTheDiceBeforeKeepTheirFacesAndBounds(): void
    {
        self::assertSame([Mark::CriticalSuccess], array_map(static fn (RolledDie $die): ?Mark => $die->critical, $this->roll('1d20+@cs', 19)->results[0]->result->sets()[1]->dice));
        self::assertSame([null, Mark::CriticalSuccess], array_map(static fn (RolledDie $die): ?Mark => $die->critical, $this->roll('[1,5]+@cs', 0)->results[0]->result->sets()[1]->dice));
        self::assertSame([Mark::CriticalFailure], array_map(static fn (RolledDie $die): ?Mark => $die->critical, $this->roll('[3]+@cf', 0)->results[0]->result->sets()[1]->dice));

        $coin = $this->roll('1d{coin}+@cs', 0)->results[0]->result->sets()[1]->dice[0];
        self::assertSame(['heads', Mark::CriticalSuccess, [1]], [$coin->face?->id, $coin->critical, $coin->rolls]);
        self::assertSame([Mark::CriticalFailure], array_map(static fn (RolledDie $die): ?Mark => $die->critical, $this->roll('1d{coin}+@cf', 1)->results[0]->result->sets()[1]->dice));
        self::assertSame([[6, 6, 2]], array_map(static fn (RolledDie $die): array => $die->rolls, $this->roll('1d6!!+@', 5, 5, 1)->results[0]->result->sets()[1]->dice));
    }

    public function testTheDiceBeforeAreThoseOfTheSameExpression(): void
    {
        $this->expectExceptionObject(new CannotRoll('"@" judges the dice of the set before it, and no set comes before it in the expression.'));
        new Roller()->roll(new Pool(new Dice(1, new StandardDie(6)), new PreviousDice()), ScriptedEngine::randomizer(0));
    }

    public function testOccurrencesAreTheSetsOfTheSameValue(): void
    {
        $set = $this->set($this->roll('6d10o2', 6, 6, 6, 2, 2, 9));

        self::assertSame([2, [[7, 7, 7], [3, 3]]], [$set->value(), $set->groups]);
        self::assertSame([[7, 7, 7]], $this->set($this->roll('6d10o2[>=5]', 6, 6, 6, 2, 2, 9))->groups);
        self::assertSame([[7, 7, 7]], $this->set($this->roll('6d10o3', 6, 6, 6, 2, 2, 9))->groups);
        self::assertSame([[7, 7], [3, 3]], $this->set($this->roll('4d10o', 2, 2, 6, 6))->groups);
        self::assertSame([[3, 3, 3], [7, 7]], $this->set($this->roll('5d10o', 6, 2, 6, 2, 2))->groups);
        self::assertSame([[3], [1]], $this->set($this->roll('2d10o1', 0, 2))->groups);
        self::assertSame([], $this->set($this->roll('3d10kh1o', 6, 9, 6))->groups);
        self::assertSame([['type' => 'dice', 'value' => 1, 'groups' => [[7, 7]]]], [array_intersect_key($this->set($this->roll('3d10dh1o', 6, 6, 9))->toArray(), ['type' => 0, 'value' => 0, 'groups' => 0])]);
    }

    public function testGroupsReachASum(): void
    {
        $set = $this->set($this->roll('5d10g10', 9, 6, 2, 4, 0));

        self::assertSame([2, [[10], [7, 3]]], [$set->value(), $set->groups]);
        self::assertSame([0, []], [$this->set($this->roll('3d6g20', 0, 0, 0))->value(), $this->set($this->roll('3d6g20', 0, 0, 0))->groups]);
        self::assertSame([[6, 2, 1, 1]], $this->set($this->roll('4d6g10', 0, 1, 5, 0))->groups);
        self::assertSame([[6, 4], [5, 5]], $this->set($this->roll('4d6g10', 3, 4, 4, 5))->groups);
    }

    /**
     * @return iterable<string, array{list<int>, int, list<list<int>>, bool}>
     */
    public static function groupings(): iterable
    {
        yield 'alone' => [[10, 12], 10, [[12], [10]], true];
        yield 'the first group found of the most' => [[7, 5, 3, 1], 10, [[7, 3]], true];
        yield 'every die in one group' => [[4, 3, 2, 1], 10, [[4, 3, 2, 1]], true];
        yield 'more groups than one pass finds' => [[6, 5, 4, 4, 1], 10, [[6, 4], [5, 4, 1]], true];
        yield 'two pairs rather than a triple' => [[9, 8, 2, 1], 10, [[9, 1], [8, 2]], true];
        yield 'none' => [[1, 1], 10, [], true];
        yield 'negative values' => [[5, -1, 6], 5, [[6], [5]], true];
        yield 'a negative die left out' => [[6, -10], 5, [[6]], true];
        yield 'nothing' => [[], 3, [], true];
        yield 'twelve dice, searched' => [[9, 9, 9, 9, 9, 9, 1, 1, 1, 1, 1, 1], 10, [[9, 1], [9, 1], [9, 1], [9, 1], [9, 1], [9, 1]], true];
        yield 'thirteen dice, greedily' => [[9, 9, 9, 9, 9, 9, 1, 1, 1, 1, 1, 1, 5], 10, [[9, 1], [9, 1], [9, 1], [9, 1], [9, 1], [9, 1]], false];
        yield 'greedily, a die that reaches the sum alone' => [[15, 12, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1], 10, [[15], [12], [1, 1, 1, 1, 1, 1, 1, 1, 1, 1]], false];
        yield 'greedily, closed by the lowest die that completes it' => [[7, 5, 3, 1, 1, 1, 1, 1, 1, 1, 1, 1, 1], 10, [[7, 3], [5, 1, 1, 1, 1, 1]], false];
    }

    /**
     * @param list<int>       $values
     * @param list<list<int>> $groups
     */
    #[DataProvider('groupings')]
    public function testGroupsAreTheMostTheDiceFormUpToTwelveDice(array $values, int $sum, array $groups, bool $optimal = true): void
    {
        self::assertSame([$groups, $optimal], Grouping::reaching($values, $sum));
    }

    public function testGroupsFoundGreedilySaySo(): void
    {
        self::assertSame([], $this->set($this->roll('12d10g10', ...array_fill(0, 12, 0)))->notices);
        self::assertSame([Notice::GreedyGroups], $this->set($this->roll('13d10g10', ...array_fill(0, 13, 0)))->notices);
        self::assertSame(['greedy-groups'], $this->set($this->roll('13d10g10', ...array_fill(0, 13, 0)))->toArray()['notices']);
    }

    public function testCriticalMarksFallOnTheDiceAsTheyFell(): void
    {
        self::assertSame([Mark::CriticalSuccess, Mark::CriticalFailure, null], $this->marks('3d20cs>18cf<3', 19, 0, 10));
        self::assertSame([Mark::CriticalSuccess, Mark::CriticalFailure], $this->marks('2d20cscf', 19, 0));
        self::assertSame([null, null], $this->marks('1d20ro20cs', 19, 4));
        self::assertSame([Mark::CriticalFailure], $this->marks('1d20min5cf', 0));
        self::assertSame([Mark::CriticalSuccess], $this->marks('1d1cscf', 0));
        self::assertSame([null], $this->marks('1d20', 19));
        self::assertSame(['critical-success'], array_column($this->set($this->roll('1d20cs', 19))->toArray()['dice'], 'critical'));
    }

    public function testLookTokensGoToTheDiceInTheirOrder(): void
    {
        self::assertSame(['accent', 'muted', 'muted', null], $this->looks('4d6look[1:accent,2:muted]', 0, 0, 0, 0));
        self::assertSame(['a', 'b', 'b'], $this->looks('3d6look[1:a,b]', 0, 0, 0));
        self::assertSame(['a', 'a'], $this->looks('2d6look[5:a]', 0, 0));
        self::assertSame(['a', 'a', 'b'], $this->looks('2d6ro1look[2:a,b]', 0, 1, 1));
    }

    public function testFunctionsRoundTheDivisionsInsideThem(): void
    {
        self::assertSame([3, 4, 7, 7], [
            $this->rollWith('floor(7/2)', RoundingMode::PositiveInfinity)->totals()[0],
            $this->rollWith('7/2', RoundingMode::PositiveInfinity)->totals()[0],
            $this->roll('ceil(floor(7/2)+7/2)')->totals()[0],
            $this->roll('ceil(7/2)+7/2')->totals()[0],
        ]);
        self::assertSame([4, -4, 4], [$this->roll('round(7/2)')->totals()[0], $this->roll('round(-7/2)')->totals()[0], $this->roll('round(10/3+1/2)')->totals()[0]]);
        self::assertSame([1, 0], [$this->rollWith('2^-1', RoundingMode::PositiveInfinity)->totals()[0], $this->rollWith('floor(2^-1)', RoundingMode::PositiveInfinity)->totals()[0]]);
        self::assertSame(RoundingMode::HalfEven, $this->rollWith('1', RoundingMode::HalfEven)->rounding);
        self::assertSame(RoundingMode::NegativeInfinity, $this->roll('1')->rounding);
    }

    public function testFunctionsGiveTheirValue(): void
    {
        self::assertSame([3, 3, 1, 6, 2], [$this->roll('abs(-3)')->totals()[0], $this->roll('abs(3)')->totals()[0], $this->roll('min(3, 1d6)', 0)->totals()[0], $this->roll('max(1d6, 3, 2)', 5)->totals()[0], $this->roll('10%4')->totals()[0]]);

        $call = $this->roll('max(1d6, 2)', 0)->results[0]->result;
        self::assertInstanceOf(FunctionResult::class, $call);
        self::assertSame([MathFunction::Max, 2, 2], [$call->function, \count($call->arguments), $call->value()]);
        self::assertSame(['type' => 'function', 'function' => 'max', 'value' => 2], \array_slice($call->toArray(), 0, 3));
        self::assertSame([['type' => 'dice'], ['type' => 'constant']], array_map(static fn (array $argument): array => array_intersect_key($argument, ['type' => 0]), $call->toArray()['arguments']));
        self::assertCount(1, $call->sets());
        self::assertSame([1, 2], array_map(static fn (DiceResult $set): int => $set->value(), $this->roll('max(1d6, 1d6)', 0, 1)->results[0]->result->sets()));
    }

    public function testAnAbsoluteValueTooLargeIsRefused(): void
    {
        $this->expectExceptionObject(CannotRoll::overflow());
        new Roller()->roll(new Pool(new FunctionCall(MathFunction::Abs, new Constant(\PHP_INT_MIN))), ScriptedEngine::randomizer());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function facesInArithmetic(): iterable
    {
        yield 'a function' => ['abs(1d{colours})'];
        yield 'a count' => ['(1d{colours})d6'];
        yield 'sides' => ['1d(1d{colours})'];
        yield 'a limit of explosions' => ['1d6!(1d{colours})'];
        yield 'a computed count of faces in arithmetic' => ['(1d2)d{colours}+1'];
    }

    #[DataProvider('facesInArithmetic')]
    public function testFacesWithoutValueStayOutOfArithmeticEverywhere(string $expression): void
    {
        $this->expectExceptionObject(CannotRoll::notNumeric('colours'));
        $this->roll($expression);
    }

    public function testFacesWithoutValueMayBeCountedFirst(): void
    {
        self::assertSame([null], $this->roll('(1d2)d{colours}', 0, 0)->totals());

        $this->expectExceptionObject(CannotRoll::unknownDie('bone'));
        $this->roll('(1d2)d{bone}');
    }

    public function testRolledDiceKnowWhatBecameOfThem(): void
    {
        $die = new RolledDie(4, rolls: [3, 1]);

        self::assertSame([4, [3, 1], 4, true], [$die->value, $die->rolls, $die->natural(), $die->counts()]);
        self::assertSame([[4], false, false], [new RolledDie(4)->rolls, new RolledDie(4, kept: false)->counts(), new RolledDie(4, rerolled: true)->counts()]);
        self::assertSame($die, $die->valued(4));
        self::assertSame([5, [3, 1], 4], [$die->valued(5)->value, $die->valued(5)->rolls, $die->valued(5)->natural()]);
        self::assertSame([true, false], [$die->exploding()->exploded, $die->exploded]);
        self::assertSame([true, false], [$die->failed()->failure, $die->failure]);
        self::assertSame([Mark::CriticalFailure, null], [$die->marked(Mark::CriticalFailure)->critical, $die->critical]);
        self::assertSame(['accent', null], [$die->looked('accent')->look, $die->look]);
        self::assertSame(['value' => 4, 'face' => null, 'origin' => 'initial', 'kept' => true, 'rerolled' => false, 'exploded' => true, 'success' => null, 'failure' => true, 'critical' => 'critical-failure', 'look' => 'a', 'rolls' => [3, 1]], $die->exploding()->failed()->marked(Mark::CriticalFailure)->looked('a')->toArray());
        self::assertSame(['critical-success', 'critical-failure'], array_map(static fn (Mark $mark): string => $mark->value, Mark::cases()));
    }

    private function roll(string $expression, int ...$offsets): PoolResult
    {
        return $this->rollWith($expression, RoundingMode::NegativeInfinity, ...$offsets);
    }

    private function rollWith(string $expression, RoundingMode $rounding, int ...$offsets): PoolResult
    {
        $custom = new CustomDice(
            new DieDefinition('colours', new Face('red', 'Red', color: '#ff0000', shape: Shape::Circle), new Face('blue', 'Blue', color: '#0000ff', shape: Shape::Square)),
            new DieDefinition('coin', new Face('heads', 'Heads', 1), new Face('tails', 'Tails', 0)),
        );

        return new Roller(customDice: $custom)->roll(new DiceParser()->parse($expression), ScriptedEngine::randomizer(...$offsets), $rounding);
    }

    private function set(PoolResult $result): DiceResult
    {
        $sets = $result->results[0]->result->sets();

        return $sets[\count($sets) - 1];
    }

    /**
     * The values of the dice listed, and the total.
     *
     * @return array{list<int>, ?int}
     */
    private function values(string $expression, int ...$offsets): array
    {
        $result = $this->roll($expression, ...$offsets);

        return [array_map(static fn (RolledDie $die): int => $die->value, $this->set($result)->dice), $result->totals()[0]];
    }

    /**
     * Whether each die is kept, and the total.
     *
     * @return array{list<bool>, ?int}
     */
    private function kept(string $expression, int ...$offsets): array
    {
        $result = $this->roll($expression, ...$offsets);

        return [array_map(static fn (RolledDie $die): bool => $die->kept, $this->set($result)->dice), $result->totals()[0]];
    }

    /**
     * @return list<?Mark>
     */
    private function marks(string $expression, int ...$offsets): array
    {
        return array_map(static fn (RolledDie $die): ?Mark => $die->critical, $this->set($this->roll($expression, ...$offsets))->dice);
    }

    /**
     * @return list<?string>
     */
    private function looks(string $expression, int ...$offsets): array
    {
        return array_map(static fn (RolledDie $die): ?string => $die->look, $this->set($this->roll($expression, ...$offsets))->dice);
    }
}
