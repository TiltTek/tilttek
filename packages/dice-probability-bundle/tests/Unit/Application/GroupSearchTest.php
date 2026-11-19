<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\DiceProbabilityBundle\Application\Exception\GroupsTooCostly;
use Tilttek\DiceProbabilityBundle\Application\GroupSearch;

#[CoversClass(GroupSearch::class)]
#[CoversClass(GroupsTooCostly::class)]
final class GroupSearchTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int}>
     */
    public static function works(): iterable
    {
        yield 'no groups' => ['4d6kh3 + 2', 0];
        yield 'one die' => ['1d6g3', 2];
        yield 'four dice' => ['4d6g6', 4 * 16];
        yield 'six dice' => ['6d6g6', 6 * 64];
        yield 'the most dice searched exhaustively' => ['12d6g12', 12 * 4_096];
        yield 'ten dice of ten sides' => ['10d10g10', 10 * 1_024];
        yield 'more dice, searched greedily' => ['13d6g12', 13 * 13];
        yield 'a count in parentheses' => ['(3)d6g6', 3 * 8];
        yield 'a set among others' => ['1d6 + 3d6g4 - 2d6g4', 3 * 8 + 2 * 4];
        yield 'a reroll keeps the count' => ['3d6r1g6', 3 * 8];
        yield 'an explosion may reach the exhaustive search' => ['2d6!g10', 12 * 4_096];
        yield 'a computed count may reach it too' => ['(1d4)d6g6', 12 * 4_096];
        yield 'a repetition of dice without groups costs nothing' => ['repeat(4d6, 3)', 0];
        yield 'a repetition counts each time' => ['repeat(4d6g6, 3)', 3 * 64];
        yield 'a repetition of a rolled number of times counts the most repetitions' => ['repeat(4d6g6, 1d3)', 100 * 64];
    }

    #[DataProvider('works')]
    public function testTheWorkOfOneSample(string $expression, int $work): void
    {
        self::assertSame($work, new GroupSearch()->work(new DiceRoller()->parse($expression)->expressions));
    }

    public function testTheMostDiceAndExplosionsOfTheLimitsBoundACountThatIsNotWritten(): void
    {
        $limits = new Limits(dice: 30, explosions: 20, repetitions: 7);
        $search = new GroupSearch($limits);

        $computed = new ComputedDice(new FunctionCall(MathFunction::Abs, new Constant(3)), new StandardDie(6), new Modifiers(groups: 6));
        self::assertSame(12 * 4_096, $search->work($computed));
        self::assertSame(8 * 256, new GroupSearch(new Limits(dice: 8))->work($computed));
        self::assertSame(0, $search->work(new FunctionCall(MathFunction::Repeat, new DiceRoller()->parse('4d6g6')->expressions[0], new Constant(-2))));
        self::assertSame(5 * 32, $search->work(new ComputedDice(new Constant(5), new StandardDie(6), new Modifiers(groups: 6))));
        self::assertSame(0, $search->work(new ComputedDice(new Constant(-5), new StandardDie(6), new Modifiers(groups: 6))));
        self::assertSame(7 * 64, $search->work(new DiceRoller()->parse('repeat(4d6g6, 1d3)')->expressions));
        // Thirty dice that explode reach fifty, and are searched greedily from the first.
        self::assertSame(50 * 50, $search->work(new DiceRoller()->parse('30d6!g10')->expressions));
        self::assertSame(12 * 4_096, $search->work(new DiceRoller()->parse('12d6!g10')->expressions));
        // Thirteen dice that explode are never searched exhaustively.
        self::assertSame(14 * 14, new GroupSearch(new Limits(explosions: 1))->work(new DiceRoller()->parse('13d6!g10')->expressions));
        self::assertSame(0, $search->work([new Constant(3), 'text', null]));
    }

    public function testTheWorkStopsAtTheLargestInteger(): void
    {
        $search = new GroupSearch(new Limits(repetitions: \PHP_INT_MAX));
        $repeated = new FunctionCall(MathFunction::Repeat, new DiceRoller()->parse('4d6g6')->expressions[0], new DiceRoller()->parse('1d6g3')->expressions[0]);

        self::assertSame(\PHP_INT_MAX, $search->work($repeated));
        self::assertSame(\PHP_INT_MAX, $search->work([$repeated, $repeated]));
        self::assertSame(\PHP_INT_MAX, new GroupSearch(new Limits(dice: \PHP_INT_MAX))->work(new ComputedDice(new FunctionCall(MathFunction::Abs, new Constant(3)), new StandardDie(6), new Modifiers(groups: 6))));
    }

    public function testTheProductAndTheSumReachTheLargestIntegerExactly(): void
    {
        $search = new GroupSearch(new Limits(repetitions: \PHP_INT_MAX));
        $one = new DiceRoller()->parse('1d6g3')->expressions[0];

        // 2 × 4611686018427387903 is one below the largest integer: it is not rounded up to it.
        self::assertSame(\PHP_INT_MAX - 1, $search->work(new FunctionCall(MathFunction::Repeat, $one, new Constant(intdiv(\PHP_INT_MAX, 2)))));
        self::assertSame(\PHP_INT_MAX, $search->work(new FunctionCall(MathFunction::Repeat, $one, new Constant(intdiv(\PHP_INT_MAX, 2) + 1))));
        // A sum that passes the largest integer stops at it.
        self::assertSame(\PHP_INT_MAX, $search->work([new FunctionCall(MathFunction::Repeat, $one, new Constant(intdiv(\PHP_INT_MAX, 2))), $one, $one]));
    }

    public function testGroupsOfNoDiceCostNothingToCheck(): void
    {
        $search = new GroupSearch(workLimit: 0);

        $this->expectNotToPerformAssertions();

        $search->check(new DiceRoller()->parse('4d6'), '4d6');
        $search->check(new DiceRoller()->parse('repeat(4d6, 3)'), 'repeat(4d6, 3)');
    }

    /**
     * Sets that took seconds to estimate are refused before any die is rolled.
     */
    public function testTheCostliestGroupsAreRefusedAtOnce(): void
    {
        $search = new GroupSearch();

        foreach (['12d6g12' => 12 * 4_096 * 5_000, '10d10g10' => 10 * 1_024 * 5_000] as $expression => $work) {
            $start = hrtime(true);

            try {
                $search->check(new DiceRoller()->parse($expression), $expression);
                self::fail(\sprintf('The groups of %s are refused.', $expression));
            } catch (GroupsTooCostly $refused) {
                self::assertSame(\sprintf('The groups of "%s" would take %d steps to search over the samples of its estimate; at most 2000000 are taken.', $expression, $work), $refused->getMessage());
                self::assertSame('refusal.groups', $refused->key());
                self::assertSame([], $refused->parameters());
            }

            self::assertLessThan(1.0, (hrtime(true) - $start) / 1e9, $expression);
        }
    }

    public function testTheWorkLimitIsReachedButNotPassed(): void
    {
        $pool = new DiceRoller()->parse('4d6g6');
        new GroupSearch(samples: 10, workLimit: 640)->check($pool, '4d6g6');
        new GroupSearch()->check(new DiceRoller()->parse('6d6g6'), '6d6g6');
        self::assertSame(2_000_000, GroupSearch::WORK_LIMIT);
        self::assertSame(12, GroupSearch::OPTIMAL);

        $this->expectException(GroupsTooCostly::class);
        $this->expectExceptionMessageIsOrContains('The groups of "4d6g6" would take 640 steps to search over the samples of its estimate; at most 639 are taken.');

        new GroupSearch(samples: 10, workLimit: 639)->check($pool, '4d6g6');
    }

    public function testThePoolOfOtherExpressionsIsWeighedToo(): void
    {
        $this->expectException(GroupsTooCostly::class);

        new GroupSearch(samples: 1, workLimit: 63)->check(new Pool(new Constant(1), new DiceRoller()->parse('4d6g6')->expressions[0]), 'several');
    }
}
