<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit\Operation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\ScoreSheets\Arithmetic;
use Tilttek\ScoreSheets\Comparison;
use Tilttek\ScoreSheets\Direction;
use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\Exception\InvalidSheet;
use Tilttek\ScoreSheets\Operation\Bonus;
use Tilttek\ScoreSheets\Operation\Majority;
use Tilttek\ScoreSheets\Operation\Operation;
use Tilttek\ScoreSheets\Operation\Operations;
use Tilttek\ScoreSheets\Operation\Product;
use Tilttek\ScoreSheets\Operation\Sum;
use Tilttek\ScoreSheets\Scoring\Step;

#[CoversClass(Operations::class)]
#[CoversClass(Sum::class)]
#[CoversClass(Product::class)]
#[CoversClass(Majority::class)]
#[CoversClass(Bonus::class)]
#[CoversClass(Comparison::class)]
#[CoversClass(Arithmetic::class)]
#[CoversClass(Step::class)]
final class OperationsTest extends TestCase
{
    public function testASumAddsItsCategories(): void
    {
        $steps = new Sum(['a', 'b'])->apply('t', [0 => ['a' => 2, 'b' => 5, 'c' => 100], 1 => ['a' => -1, 'b' => 0, 'c' => 0]]);

        self::assertSame(['category' => 't', 'operation' => 'sum', 'operands' => ['a' => 2, 'b' => 5], 'value' => 7], $steps[0]->toArray());
        self::assertSame(-1, $steps[1]->value);
        self::assertSame(['a', 'b'], new Sum(['a', 'b'])->reads());
    }

    public function testAProductMultipliesItsCategoriesAndItsFactor(): void
    {
        $steps = new Product(['a', 'b'], 3)->apply('t', [0 => ['a' => 2, 'b' => 5], 1 => ['a' => 0, 'b' => 9]]);

        self::assertSame(['category' => 't', 'operation' => 'product', 'operands' => ['a' => 2, 'b' => 5, '#factor' => 3], 'value' => 30], $steps[0]->toArray());
        self::assertSame(0, $steps[1]->value);
        self::assertSame(7, new Product(['a'])->apply('t', [['a' => 7]])[0]->value);
        self::assertSame(['a'], new Product(['a'])->reads());
    }

    public function testAProductTooLargeIsRefused(): void
    {
        $this->expectExceptionObject(InvalidEntry::because('The category "t" is too large to compute.'));

        new Product(array_fill(0, 16, 'a'), 1_000_000)->apply('t', [['a' => 1_000_000]]);
    }

    public function testASumTooLargeIsRefused(): void
    {
        self::assertSame(\PHP_INT_MAX, Arithmetic::add(\PHP_INT_MAX - 1, 1, 't'));

        $this->expectExceptionObject(InvalidEntry::because('The category "t" is too large to compute.'));
        Arithmetic::add(\PHP_INT_MAX, 1, 't');
    }

    public function testAMajorityGivesPointsByPlace(): void
    {
        $table = [0 => ['b' => 3], 1 => ['b' => 7], 2 => ['b' => 3], 3 => ['b' => 1], 4 => ['b' => 0]];
        $steps = new Majority('b', [6, 3, 1])->apply('fleet', $table);

        self::assertSame([3, 6, 3, 0, 0], array_map(static fn (Step $step): int => $step->value, $steps));
        self::assertSame([2, 1, 2, 4, 5], array_map(static fn (Step $step): int => $step->operands['#place'], $steps));
        self::assertSame(['b' => 3, '#place' => 2], $steps[0]->operands);
        self::assertSame('majority', $steps[0]->operation);
        self::assertSame([0, 1, 2, 3, 4], array_keys($steps), 'The steps follow the order of the table.');
    }

    public function testAMajorityOfTheFewestAndItsThreshold(): void
    {
        $table = [0 => ['b' => 3], 1 => ['b' => 7], 2 => ['b' => 0]];

        self::assertSame([3, 0, 6], array_map(static fn (Step $step): int => $step->value, new Majority('b', [6, 3], Direction::Fewest)->apply('m', $table)));
        self::assertSame([6, 3, 0], array_map(static fn (Step $step): int => $step->value, new Majority('b', [6, 3], Direction::Fewest, 1)->apply('m', $table)));
        self::assertSame([3, 6, 0], array_map(static fn (Step $step): int => $step->value, new Majority('b', [6, 3], Direction::Most, 3)->apply('m', $table)));
        self::assertSame([0, 6, 0], array_map(static fn (Step $step): int => $step->value, new Majority('b', [6, 3], Direction::Most, 4)->apply('m', $table)));
        self::assertSame(0, new Majority('b', [6, 3], Direction::Most, 4)->apply('m', $table)[0]->operands['#place']);
        self::assertSame(['b'], new Majority('b', [1])->reads());
    }

    public function testATieForFirstSkipsTheSecondPlace(): void
    {
        $steps = new Majority('b', [6, 3])->apply('m', [0 => ['b' => 5], 1 => ['b' => 5], 2 => ['b' => 4]]);

        self::assertSame([6, 6, 0], array_map(static fn (Step $step): int => $step->value, $steps));
    }

    public function testABonusMeetsItsCondition(): void
    {
        $table = [0 => ['l' => 9], 1 => ['l' => 10], 2 => ['l' => 11]];

        self::assertSame([0, 5, 5], array_map(static fn (Step $step): int => $step->value, new Bonus('l', Comparison::AtLeast, 10, 5)->apply('x', $table)));
        self::assertSame([5, 5, 0], array_map(static fn (Step $step): int => $step->value, new Bonus('l', Comparison::AtMost, 10, 5)->apply('x', $table)));
        self::assertSame([0, -2, 0], array_map(static fn (Step $step): int => $step->value, new Bonus('l', Comparison::Equals, 10, -2)->apply('x', $table)));
        self::assertSame(['category' => 'x', 'operation' => 'bonus', 'operands' => ['l' => 9, '#threshold' => 10], 'value' => 0], new Bonus('l', Comparison::AtLeast, 10, 5)->apply('x', $table)[0]->toArray());
        self::assertSame(['l'], new Bonus('l', Comparison::AtLeast, 10, 5)->reads());
    }

    /**
     * @return iterable<string, array{Operation, array<string, mixed>}>
     */
    public static function operations(): iterable
    {
        yield 'sum' => [new Sum(['a', 'b']), ['type' => 'sum', 'of' => ['a', 'b']]];
        yield 'product' => [new Product(['a'], -4), ['type' => 'product', 'of' => ['a'], 'factor' => -4]];
        yield 'majority' => [new Majority('a', [6, 3]), ['type' => 'majority', 'of' => 'a', 'points' => [6, 3], 'prefer' => 'most', 'at_least' => null]];
        yield 'majority of the fewest' => [new Majority('a', [1], Direction::Fewest, 2), ['type' => 'majority', 'of' => 'a', 'points' => [1], 'prefer' => 'fewest', 'at_least' => 2]];
        yield 'bonus at least' => [new Bonus('a', Comparison::AtLeast, 10, 5), ['type' => 'bonus', 'of' => 'a', 'at_least' => 10, 'points' => 5]];
        yield 'bonus at most' => [new Bonus('a', Comparison::AtMost, 0, 1), ['type' => 'bonus', 'of' => 'a', 'at_most' => 0, 'points' => 1]];
        yield 'bonus equals' => [new Bonus('a', Comparison::Equals, 3, 7), ['type' => 'bonus', 'of' => 'a', 'equals' => 3, 'points' => 7]];
    }

    /**
     * @param array<string, mixed> $array
     */
    #[DataProvider('operations')]
    public function testAnOperationGoesToAnArrayAndBack(Operation $operation, array $array): void
    {
        self::assertSame($array, $operation->toArray());
        self::assertEquals($operation, Operations::fromArray($array));
    }

    public function testTheDefaultsOfAnOperation(): void
    {
        self::assertSame(1, Operations::fromArray(['type' => 'product', 'of' => ['a']])->toArray()['factor']);
        self::assertEquals(new Majority('a', [2]), Operations::fromArray(['type' => 'majority', 'of' => 'a', 'points' => [2]]));
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function badOperations(): iterable
    {
        $field = static fn (string $name): string => \sprintf('The field "%s" of the operation is missing or of the wrong type.', $name);

        yield 'not an array' => ['sum', 'An operation is an object.'];
        yield 'no type' => [[], 'An operation is sum, product, majority or bonus.'];
        yield 'unknown type' => [['type' => 'average'], 'An operation is sum, product, majority or bonus.'];
        yield 'no categories' => [['type' => 'sum'], $field('of')];
        yield 'categories not a list' => [['type' => 'sum', 'of' => ['x' => 'a']], $field('of')];
        yield 'category not a string' => [['type' => 'sum', 'of' => [1]], $field('of')];
        yield 'factor not an integer' => [['type' => 'product', 'of' => ['a'], 'factor' => '2'], $field('factor')];
        yield 'majority of a list' => [['type' => 'majority', 'of' => ['a'], 'points' => [1]], $field('of')];
        yield 'no points' => [['type' => 'majority', 'of' => 'a'], $field('points')];
        yield 'points not a list' => [['type' => 'majority', 'of' => 'a', 'points' => ['x' => 1]], $field('points')];
        yield 'point not an integer' => [['type' => 'majority', 'of' => 'a', 'points' => [1.5]], $field('points')];
        yield 'preference' => [['type' => 'majority', 'of' => 'a', 'points' => [1], 'prefer' => 'more'], $field('prefer')];
        yield 'preference not a string' => [['type' => 'majority', 'of' => 'a', 'points' => [1], 'prefer' => 1], $field('prefer')];
        yield 'threshold not an integer' => [['type' => 'majority', 'of' => 'a', 'points' => [1], 'at_least' => '1'], $field('at_least')];
        yield 'bonus without condition' => [['type' => 'bonus', 'of' => 'a', 'points' => 1], 'A bonus has one condition: at_least, at_most or equals.'];
        yield 'bonus of two conditions' => [['type' => 'bonus', 'of' => 'a', 'at_least' => 1, 'at_most' => 3, 'points' => 1], 'A bonus has one condition: at_least, at_most or equals.'];
        yield 'bonus threshold not an integer' => [['type' => 'bonus', 'of' => 'a', 'equals' => null, 'points' => 1], $field('equals')];
        yield 'bonus without points' => [['type' => 'bonus', 'of' => 'a', 'equals' => 1], $field('points')];
        yield 'bonus without category' => [['type' => 'bonus', 'equals' => 1, 'points' => 1], $field('of')];
    }

    #[DataProvider('badOperations')]
    public function testDataThatIsNotAnOperationIsRefused(mixed $data, string $message): void
    {
        $this->expectExceptionObject(InvalidSheet::because($message));

        Operations::fromArray($data);
    }

    /**
     * @return iterable<string, array{callable(): Operation, string}>
     */
    public static function outOfBounds(): iterable
    {
        yield 'sum of none' => [static fn (): Operation => new Sum([]), 'A sum reads 1 to 16 categories.'];
        yield 'sum of 17' => [static fn (): Operation => new Sum(array_fill(0, 17, 'a')), 'A sum reads 1 to 16 categories.'];
        yield 'sum of a bad key' => [static fn (): Operation => new Sum(['1']), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'product of none' => [static fn (): Operation => new Product([]), 'A product reads 1 to 16 categories.'];
        yield 'factor too large' => [static fn (): Operation => new Product(['a'], 1_000_001), 'The factor of a product is between -1000000 and 1000000.'];
        yield 'factor too small' => [static fn (): Operation => new Product(['a'], -1_000_001), 'The factor of a product is between -1000000 and 1000000.'];
        yield 'majority of a bad key' => [static fn (): Operation => new Majority('', [1]), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'majority of no points' => [static fn (): Operation => new Majority('a', []), 'A majority gives points to 1 to 8 places.'];
        yield 'majority of nine places' => [static fn (): Operation => new Majority('a', array_fill(0, 9, 1)), 'A majority gives points to 1 to 8 places.'];
        yield 'majority points too large' => [static fn (): Operation => new Majority('a', [1, 1_000_001]), 'The points of a majority is between -1000000 and 1000000.'];
        yield 'majority threshold too large' => [static fn (): Operation => new Majority('a', [1], atLeast: 1_000_001), 'The threshold of a majority is between -1000000 and 1000000.'];
        yield 'bonus of a bad key' => [static fn (): Operation => new Bonus('a b', Comparison::AtLeast, 1, 1), 'A category key is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.'];
        yield 'bonus threshold too small' => [static fn (): Operation => new Bonus('a', Comparison::AtLeast, -1_000_001, 1), 'The threshold of a bonus is between -1000000 and 1000000.'];
        yield 'bonus points too large' => [static fn (): Operation => new Bonus('a', Comparison::AtLeast, 1, 1_000_001), 'The points of a bonus is between -1000000 and 1000000.'];
    }

    #[DataProvider('outOfBounds')]
    public function testAnOperationOutOfBoundsIsRefused(callable $make, string $message): void
    {
        $this->expectExceptionObject(InvalidSheet::because($message));

        $make();
    }

    public function testTheWidestBoundsAreAccepted(): void
    {
        self::assertCount(16, new Sum(array_fill(0, 16, 'a'))->of);
        self::assertSame(-1_000_000, new Product(['a'], -1_000_000)->factor);
        self::assertSame(1_000_000, new Product(['a'], 1_000_000)->factor);
        self::assertCount(8, new Majority('a', array_fill(0, 8, 1_000_000), atLeast: -1_000_000)->points);
        self::assertSame(-1_000_000, new Bonus('a', Comparison::AtLeast, -1_000_000, -1_000_000)->points);
    }
}
