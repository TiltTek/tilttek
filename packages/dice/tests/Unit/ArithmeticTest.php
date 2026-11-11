<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Rolling\Arithmetic;

#[CoversClass(Arithmetic::class)]
#[CoversClass(CannotRoll::class)]
final class ArithmeticTest extends TestCase
{
    /**
     * The quotients by mode, in the order towards zero, away from zero, down, up, half away, half towards, half to
     * even, half to odd.
     *
     * @return iterable<string, array{int, int, list<int>}>
     */
    public static function divisions(): iterable
    {
        yield 'a half' => [7, 2, [3, 4, 3, 4, 4, 3, 4, 3]];
        yield 'a negative half' => [-7, 2, [-3, -4, -4, -3, -4, -3, -4, -3]];
        yield 'a half by a negative divisor' => [7, -2, [-3, -4, -4, -3, -4, -3, -4, -3]];
        yield 'a half of both negative' => [-7, -2, [3, 4, 3, 4, 4, 3, 4, 3]];
        yield 'an even half' => [5, 2, [2, 3, 2, 3, 3, 2, 2, 3]];
        yield 'a negative even half' => [-5, 2, [-2, -3, -3, -2, -3, -2, -2, -3]];
        yield 'below the half' => [7, 3, [2, 3, 2, 3, 2, 2, 2, 2]];
        yield 'above the half' => [8, 3, [2, 3, 2, 3, 3, 3, 3, 3]];
        yield 'negative, below the half' => [-7, 3, [-2, -3, -3, -2, -2, -2, -2, -2]];
        yield 'negative, above the half' => [-8, 3, [-2, -3, -3, -2, -3, -3, -3, -3]];
        yield 'exact' => [6, 3, [2, 2, 2, 2, 2, 2, 2, 2]];
        yield 'exact and negative' => [-6, 3, [-2, -2, -2, -2, -2, -2, -2, -2]];
        yield 'by minus one' => [5, -1, [-5, -5, -5, -5, -5, -5, -5, -5]];
        yield 'zero' => [0, -3, [0, 0, 0, 0, 0, 0, 0, 0]];
        yield 'smaller' => [1, 3, [0, 1, 0, 1, 0, 0, 0, 0]];
        yield 'the smallest integer by the largest' => [\PHP_INT_MIN, \PHP_INT_MAX, [-1, -2, -2, -1, -1, -1, -1, -1]];
        yield 'the largest integer by the smallest' => [\PHP_INT_MAX, \PHP_INT_MIN, [0, -1, -1, 0, -1, -1, -1, -1]];
        yield 'one by the smallest integer' => [1, \PHP_INT_MIN, [0, -1, -1, 0, 0, 0, 0, 0]];
    }

    /**
     * @param list<int> $quotients
     */
    #[DataProvider('divisions')]
    public function testADivisionRoundsAsItsModeSays(int $left, int $right, array $quotients): void
    {
        self::assertSame($quotients, array_map(static fn (RoundingMode $rounding): int => Arithmetic::divide($left, $right, $rounding), $this->modes()));
    }

    public function testADivisionByZeroIsRefused(): void
    {
        $this->expectExceptionObject(CannotRoll::divisionByZero());
        Arithmetic::divide(1, 0, RoundingMode::NegativeInfinity);
    }

    public function testTheSmallestIntegerByMinusOneOverflows(): void
    {
        $this->expectExceptionObject(CannotRoll::overflow());
        Arithmetic::divide(\PHP_INT_MIN, -1, RoundingMode::TowardsZero);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function remainders(): iterable
    {
        yield 'positive' => [7, 3, 1];
        yield 'a negative dividend' => [-7, 3, 2];
        yield 'a negative divisor' => [7, -3, -2];
        yield 'both negative' => [-7, -3, -1];
        yield 'exact' => [6, 3, 0];
        yield 'exact and negative' => [-6, 3, 0];
        yield 'exact by a negative divisor' => [6, -3, 0];
        yield 'the smallest integer by minus one' => [\PHP_INT_MIN, -1, 0];
    }

    #[DataProvider('remainders')]
    public function testARemainderHasTheSignOfTheDivisor(int $left, int $right, int $remainder): void
    {
        self::assertSame($remainder, Arithmetic::modulo($left, $right));
    }

    public function testARemainderByZeroIsRefused(): void
    {
        $this->expectExceptionObject(CannotRoll::divisionByZero());
        Arithmetic::modulo(1, 0);
    }

    /**
     * @return iterable<string, array{int, int, int}>
     */
    public static function powers(): iterable
    {
        yield 'of two' => [2, 10, 1024];
        yield 'to zero' => [5, 0, 1];
        yield 'zero to zero' => [0, 0, 1];
        yield 'zero' => [0, 3, 0];
        yield 'to one' => [7, 1, 7];
        yield 'to three' => [3, 3, 27];
        yield 'of a negative base, odd' => [-2, 3, -8];
        yield 'of a negative base, even' => [-2, 2, 4];
        yield 'the largest power of two' => [2, 62, 4_611_686_018_427_387_904];
        yield 'the smallest integer' => [-2, 63, \PHP_INT_MIN];
        yield 'of one, far' => [1, 1_000_000_000, 1];
        yield 'of minus one, far and odd' => [-1, 1_000_000_001, -1];
    }

    #[DataProvider('powers')]
    public function testAPowerIsAnInteger(int $base, int $exponent, int $power): void
    {
        self::assertSame($power, Arithmetic::power($base, $exponent, RoundingMode::NegativeInfinity));
    }

    /**
     * @return iterable<string, array{int, int}>
     */
    public static function overflowingPowers(): iterable
    {
        yield 'two to sixty-three' => [2, 63];
        yield 'three to forty' => [3, 40];
        yield 'ten to twenty' => [10, 20];
        yield 'minus two to sixty-four' => [-2, 64];
    }

    #[DataProvider('overflowingPowers')]
    public function testAPowerTooLargeIsRefused(int $base, int $exponent): void
    {
        $this->expectExceptionObject(CannotRoll::overflow());
        Arithmetic::power($base, $exponent, RoundingMode::NegativeInfinity);
    }

    /**
     * The powers by mode, as for the divisions.
     *
     * @return iterable<string, array{int, int, list<int>}>
     */
    public static function negativePowers(): iterable
    {
        yield 'a half' => [2, -1, [0, 1, 0, 1, 1, 0, 0, 1]];
        yield 'a negative half' => [-2, -1, [0, -1, -1, 0, -1, 0, 0, -1]];
        yield 'a quarter' => [2, -2, [0, 1, 0, 1, 0, 0, 0, 0]];
        yield 'a negative eighth' => [-2, -3, [0, -1, -1, 0, 0, 0, 0, 0]];
        yield 'of one' => [1, -5, [1, 1, 1, 1, 1, 1, 1, 1]];
        yield 'of minus one, odd' => [-1, -3, [-1, -1, -1, -1, -1, -1, -1, -1]];
        yield 'of minus one, even' => [-1, -2, [1, 1, 1, 1, 1, 1, 1, 1]];
        yield 'beyond the integers' => [2, -100, [0, 1, 0, 1, 0, 0, 0, 0]];
        yield 'beyond the integers, negative' => [-2, -101, [0, -1, -1, 0, 0, 0, 0, 0]];
        yield 'beyond the integers, negative base, even' => [-2, -100, [0, 1, 0, 1, 0, 0, 0, 0]];
    }

    /**
     * @param list<int> $powers
     */
    #[DataProvider('negativePowers')]
    public function testANegativeExponentDividesOneAndRounds(int $base, int $exponent, array $powers): void
    {
        self::assertSame($powers, array_map(static fn (RoundingMode $rounding): int => Arithmetic::power($base, $exponent, $rounding), $this->modes()));
    }

    public function testZeroToANegativePowerDividesByZero(): void
    {
        $this->expectExceptionObject(CannotRoll::divisionByZero());
        Arithmetic::power(0, -1, RoundingMode::NegativeInfinity);
    }

    public function testSumsDifferencesAndProductsStayIntegers(): void
    {
        self::assertSame([5, -1, 6], [Arithmetic::add(2, 3), Arithmetic::subtract(2, 3), Arithmetic::multiply(2, 3)]);

        foreach ([static fn (): int => Arithmetic::add(\PHP_INT_MAX, 1), static fn (): int => Arithmetic::subtract(\PHP_INT_MIN, 1), static fn (): int => Arithmetic::multiply(\PHP_INT_MAX, 2)] as $overflow) {
            try {
                $overflow();
                self::fail('The overflow passed.');
            } catch (CannotRoll $cannotRoll) {
                self::assertSame('The result is too large for an integer.', $cannotRoll->getMessage());
            }
        }

        self::assertSame(3, Arithmetic::integer(3));
        $this->expectExceptionObject(CannotRoll::overflow());
        Arithmetic::integer(1.5);
    }

    /**
     * @return list<RoundingMode>
     */
    private function modes(): array
    {
        return [
            RoundingMode::TowardsZero,
            RoundingMode::AwayFromZero,
            RoundingMode::NegativeInfinity,
            RoundingMode::PositiveInfinity,
            RoundingMode::HalfAwayFromZero,
            RoundingMode::HalfTowardsZero,
            RoundingMode::HalfEven,
            RoundingMode::HalfOdd,
        ];
    }
}
