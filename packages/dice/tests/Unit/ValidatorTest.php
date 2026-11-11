<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Expression\ByEachFace;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Composite;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Junction;
use Tilttek\Dice\Expression\Modulo;
use Tilttek\Dice\Expression\Range;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Validator;

#[CoversClass(Comparison::class)]
#[CoversClass(Range::class)]
#[CoversClass(Modulo::class)]
#[CoversClass(Composite::class)]
#[CoversClass(Junction::class)]
#[CoversClass(Scope::class)]
#[CoversClass(Condition::class)]
#[CoversTrait(ByEachFace::class)]
final class ValidatorTest extends TestCase
{
    /**
     * @return iterable<string, array{Validator, list<int>}>
     */
    public static function validators(): iterable
    {
        $equal = static fn (int $value): Comparison => new Comparison(Comparator::Equal, $value);

        yield 'not equal' => [new Comparison(Comparator::NotEqual, 3), [-2, -1, 0, 1, 2, 4, 5, 6]];
        yield 'a range' => [new Range(2, 4), [2, 3, 4]];
        yield 'a range of one' => [new Range(3, 3), [3]];
        yield 'the even values' => [new Modulo(2, $equal(0)), [-2, 0, 2, 4, 6]];
        yield 'a remainder of one' => [new Modulo(3, $equal(1)), [-2, 1, 4]];
        yield 'a remainder unlike' => [new Modulo(3, new Comparison(Comparator::NotEqual, 0)), [-2, -1, 1, 2, 4, 5]];
        yield 'a remainder of a negative value' => [new Modulo(4, $equal(3)), [-1, 3]];
        yield 'both' => [new Composite(Junction::And, new Comparison(Comparator::Greater, 1), new Comparison(Comparator::Less, 4)), [2, 3]];
        yield 'either' => [new Composite(Junction::Or, $equal(1), $equal(6)), [1, 6]];
        yield 'exactly one' => [new Composite(Junction::Xor, new Range(1, 4), new Range(3, 6)), [1, 2, 5, 6]];
    }

    /**
     * @param list<int> $matching among the values from -2 to 6
     */
    #[DataProvider('validators')]
    public function testAValidatorMatchesItsValues(Validator $validator, array $matching): void
    {
        self::assertSame($matching, array_values(array_filter(range(-2, 6), $validator->matches(...))));
    }

    public function testANotEqualComparisonIsNotPlain(): void
    {
        self::assertSame(
            [true, false, true, true, true, true],
            array_map(static fn (Comparator $comparator): bool => new Comparison($comparator, 1)->isPlain(), Comparator::cases()),
        );
    }

    /**
     * @return iterable<string, array{Validator, int, int, bool}>
     */
    public static function everyFace(): iterable
    {
        $equal = static fn (int $value): Comparison => new Comparison(Comparator::Equal, $value);

        yield 'not equal, below the faces' => [new Comparison(Comparator::NotEqual, 0), 1, 6, true];
        yield 'not equal, above the faces' => [new Comparison(Comparator::NotEqual, 7), 1, 6, true];
        yield 'not equal, the lowest face' => [new Comparison(Comparator::NotEqual, 1), 1, 6, false];
        yield 'not equal, the highest face' => [new Comparison(Comparator::NotEqual, 6), 1, 6, false];
        yield 'a range around' => [new Range(1, 6), 1, 6, true];
        yield 'a range from above' => [new Range(2, 6), 1, 6, false];
        yield 'a range up to below' => [new Range(1, 5), 1, 6, false];
        yield 'both, each all' => [new Composite(Junction::And, new Range(0, 6), new Range(1, 9)), 1, 6, true];
        yield 'both, the left not all' => [new Composite(Junction::And, new Range(2, 6), new Range(1, 9)), 1, 6, false];
        yield 'both, the right not all' => [new Composite(Junction::And, new Range(1, 9), new Range(2, 6)), 1, 6, false];
        yield 'either, together all' => [new Composite(Junction::Or, new Range(1, 3), new Range(4, 6)), 1, 6, true];
        yield 'either, a face missed' => [new Composite(Junction::Or, new Range(1, 3), new Range(5, 6)), 1, 6, false];
        yield 'either, the highest face missed' => [new Composite(Junction::Or, new Range(1, 3), new Range(4, 5)), 1, 6, false];
        yield 'exactly one, all' => [new Composite(Junction::Xor, $equal(1), new Range(2, 6)), 1, 6, true];
        yield 'both, past a thousand faces' => [new Composite(Junction::And, new Range(0, 6000), new Range(1, 9000)), 1, 5000, true];
        yield 'remainder, one face' => [new Modulo(2, $equal(0)), 4, 4, true];
        yield 'remainder, two faces' => [new Modulo(2, $equal(0)), 4, 5, false];
        yield 'remainder by one' => [new Modulo(1, $equal(0)), -500, 499, true];
        yield 'remainder by one, past a thousand faces' => [new Modulo(1, $equal(0)), -500, 500, false];
    }

    #[DataProvider('everyFace')]
    public function testAValidatorKnowsWhetherEveryFaceMatches(Validator $validator, int $lowest, int $highest, bool $all): void
    {
        self::assertSame($all, $validator->matchesAll($lowest, $highest));
    }

    public function testARangeGoesUp(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A range goes from its lower bound to its higher one; 3..2 does not.'));
        new Range(3, 2);
    }

    public function testARemainderIsTakenByOneAtLeast(): void
    {
        $this->expectExceptionObject(InvalidDefinition::because('A remainder is taken by a divisor of at least 1; 0 is not.'));
        new Modulo(0, new Comparison(Comparator::Equal, 0));
    }

    public function testAndBindsTighter(): void
    {
        self::assertSame([2, 1, 1], array_map(static fn (Junction $junction): int => $junction->precedence(), Junction::cases()));
    }

    public function testAConditionJudgesEachFinalValueByDefault(): void
    {
        $condition = new Condition(new Range(1, 2));

        self::assertSame(Scope::Value, $condition->scope);
        self::assertSame([true, true, false, false, false], array_map(static fn (Scope $scope): bool => $scope->eachDie(), Scope::cases()));
    }
}
