<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Parser\DiceParser;

#[CoversClass(Limits::class)]
#[CoversClass(LimitExceeded::class)]
final class LimitsTest extends TestCase
{
    public function testTheDefaultsAreGenerous(): void
    {
        $limits = new Limits();

        self::assertSame([100, 1000, 100, 10, 256, 100], [$limits->dice, $limits->sides, $limits->explosions, $limits->expressions, $limits->length, $limits->rerolls]);
    }

    /**
     * @return iterable<string, array{callable(): Limits, string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'dice' => [static fn (): Limits => new Limits(dice: 0), 'The limit of dice is at least 1; 0 is not.'];
        yield 'sides' => [static fn (): Limits => new Limits(sides: 0), 'The limit of sides is at least 1; 0 is not.'];
        yield 'explosions' => [static fn (): Limits => new Limits(explosions: 0), 'The limit of explosions is at least 1; 0 is not.'];
        yield 'expressions' => [static fn (): Limits => new Limits(expressions: 0), 'The limit of expressions is at least 1; 0 is not.'];
        yield 'length' => [static fn (): Limits => new Limits(length: -1), 'The limit of length is at least 1; -1 is not.'];
        yield 'rerolls' => [static fn (): Limits => new Limits(rerolls: 0), 'The limit of rerolls is at least 1; 0 is not.'];
    }

    /**
     * @param callable(): Limits $limits
     */
    #[DataProvider('invalidLimits')]
    public function testARefusedLimitSaysWhich(callable $limits, string $message): void
    {
        $this->expectExceptionObject(InvalidDefinition::because($message));
        $limits();
    }

    public function testOneOfEachIsAllowed(): void
    {
        self::assertSame([1, 1], [new Limits(1, 1, 1, 1, 1, 1)->length, new Limits(1, 1, 1, 1, 1, 1)->rerolls]);
    }

    public function testTheDiceAreCountedThroughTheWholeTree(): void
    {
        $tree = new Operation(Operator::Add, new Negation(new Dice(2, new StandardDie(6))), new Operation(Operator::Multiply, new Dice(3, new FudgeDie()), new Constant(4)));
        $limits = new Limits(dice: 7, sides: 6, expressions: 2);
        $limits->check(new Pool($tree, new Dice(2, new StandardDie(6))));
        $this->addToAssertionCount(1);

        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', 7, 8));
        $limits->check(new Pool($tree, new Dice(3, new StandardDie(6))));
    }

    public function testTheSidesAreCheckedInsideTheTree(): void
    {
        new Limits(sides: 6)->check(new Pool(new Negation(new Dice(1, new StandardDie(6)))));
        $this->addToAssertionCount(1);

        $this->expectExceptionObject(LimitExceeded::of('sides of a die', 6, 7));
        new Limits(sides: 6)->check(new Pool(new Negation(new Dice(1, new StandardDie(7)))));
    }

    public function testTheExpressionsOfAPoolAreCounted(): void
    {
        new Limits(expressions: 2)->check(new Pool(new Constant(1), new Constant(2)));
        $this->addToAssertionCount(1);

        $this->expectExceptionObject(LimitExceeded::of('expressions of a pool', 2, 3));
        new Limits(expressions: 2)->check(new Pool(new Constant(1), new Constant(2), new Constant(3)));
    }

    public function testTheLengthAndTheExplosionsAreBoundsIncluded(): void
    {
        $limits = new Limits(explosions: 3, length: 3);
        $limits->checkLength('été');
        $limits->checkExplosions(3);
        $this->addToAssertionCount(2);

        try {
            $limits->checkLength('été!');
            self::fail('The length passed.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame('The characters of an expression are limited to 3; this asks for 4.', $limitExceeded->getMessage());
        }

        $this->expectExceptionObject(LimitExceeded::of('explosions of a roll', 3, 4));
        $limits->checkExplosions(4);
    }

    public function testTheRerollsDiceAndSidesOfARollAreBoundsIncluded(): void
    {
        $limits = new Limits(dice: 2, sides: 2, rerolls: 2);
        $limits->checkRerolls(2);
        $limits->checkDice(2);
        $limits->checkSides(2);
        $this->addToAssertionCount(3);

        foreach ([[$limits->checkDice(...), 'The dice of a roll are limited to 2; this asks for 3.'], [$limits->checkSides(...), 'The sides of a die are limited to 2; this asks for 3.']] as [$check, $message]) {
            try {
                $check(3);
                self::fail('The limit passed.');
            } catch (LimitExceeded $limitExceeded) {
                self::assertSame($message, $limitExceeded->getMessage());
            }
        }

        $this->expectExceptionObject(LimitExceeded::of('rerolls of a roll', 2, 3));
        $limits->checkRerolls(3);
    }

    /**
     * @return iterable<string, array{string, int}>
     */
    public static function counted(): iterable
    {
        yield 'a count rolled first' => ['(2d6)d6', 2];
        yield 'sides rolled first' => ['2d(3d6)', 3];
        yield 'a limit of explosions rolled first' => ['2d6!(3d4)', 5];
        yield 'a count rolled first with a limit' => ['(1d4)d6!(2d4)', 3];
        yield 'a list of values' => ['[1,2,3]+@', 3];
        yield 'a function' => ['max(2d6, 3d6)', 5];
    }

    #[DataProvider('counted')]
    public function testTheDiceKnownBeforeTheRollAreCountedEverywhere(string $expression, int $dice): void
    {
        $pool = new DiceParser()->parse($expression);
        new Limits(dice: $dice)->check($pool);
        $this->addToAssertionCount(1);

        $this->expectExceptionObject(LimitExceeded::of('dice of a roll', $dice - 1, $dice));
        new Limits(dice: $dice - 1)->check($pool);
    }

    /**
     * @return iterable<string, array{Node, int}>
     */
    public static function sides(): iterable
    {
        yield 'a die on a range' => [new Dice(1, new RangeDie(-1, 4)), 6];
        yield 'a die on a range, in a count rolled first' => [new ComputedDice(new Constant(1), new RangeDie(1, 6)), 6];
        yield 'a die, in a count rolled first' => [new ComputedDice(new Dice(1, new StandardDie(6)), new StandardDie(4)), 6];
        yield 'a die whose sides are rolled' => [new ComputedDice(new Constant(1), new Dice(1, new StandardDie(6))), 6];
    }

    #[DataProvider('sides')]
    public function testTheSidesKnownBeforeTheRollAreChecked(Node $node, int $sides): void
    {
        new Limits(sides: $sides)->check(new Pool($node));
        $this->addToAssertionCount(1);

        $this->expectExceptionObject(LimitExceeded::of('sides of a die', $sides - 1, $sides));
        new Limits(sides: $sides - 1)->check(new Pool($node));
    }
}
