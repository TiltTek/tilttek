<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit\Exact;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceProbability\Exact\Natural;

#[CoversClass(Natural::class)]
final class NaturalTest extends TestCase
{
    public function testAnIntegerIsWrittenBack(): void
    {
        self::assertSame('0', (string) Natural::of(0));
        self::assertSame('7', (string) Natural::of(7));
        self::assertSame('1000000000', (string) Natural::of(1_000_000_000));
        self::assertSame('1000000007', (string) Natural::of(1_000_000_007));
        self::assertSame((string) \PHP_INT_MAX, (string) Natural::of(\PHP_INT_MAX));
        self::assertSame('0', (string) Natural::zero());
        self::assertSame('1', (string) Natural::one());
    }

    public function testDigitsAreReadWhateverTheirLength(): void
    {
        self::assertSame('123456789012345678901234567890', (string) Natural::parse('123456789012345678901234567890'));
        self::assertSame('1000000000000000000', (string) Natural::parse('0001000000000000000000'));
        self::assertSame('0', (string) Natural::parse('000'));
        self::assertTrue(Natural::parse('000000000000')->isZero());
        self::assertSame('987654321', (string) Natural::parse('987654321'));
        self::assertSame('9876543210', (string) Natural::parse('9876543210'));
    }

    public function testANegativeIntegerIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A natural number is zero or more; -1 is not.');

        Natural::of(-1);
    }

    #[DataProvider('notDigits')]
    public function testTextOtherThanDigitsIsRefused(string $text): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A natural number is written with decimal digits only; "%s" is not.', $text));

        Natural::parse($text);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notDigits(): iterable
    {
        yield 'empty' => [''];
        yield 'sign' => ['-5'];
        yield 'letter after' => ['5a'];
        yield 'letter before' => ['a5'];
        yield 'new line after' => ["5\n"];
    }

    public function testAdditionCarriesAcrossLimbs(): void
    {
        self::assertSame('1000000000', (string) Natural::of(999_999_999)->add(Natural::one()));
        self::assertSame('1000000000000000000', (string) Natural::parse('999999999999999999')->add(Natural::one()));
        self::assertSame('2000000000', (string) Natural::of(1_000_000_000)->add(Natural::of(1_000_000_000)));
        self::assertSame('5', (string) Natural::zero()->add(Natural::of(5)));
        self::assertSame('1999999998', (string) Natural::of(999_999_999)->add(Natural::of(999_999_999)));
        self::assertSame('1000000001', (string) Natural::of(1)->add(Natural::of(1_000_000_000)));
    }

    public function testSubtractionBorrowsAcrossLimbs(): void
    {
        self::assertSame('999999999', (string) Natural::of(1_000_000_000)->subtract(Natural::one()));
        self::assertSame('999999999999999999', (string) Natural::parse('1000000000000000000')->subtract(Natural::one()));
        self::assertTrue(Natural::of(5)->subtract(Natural::of(5))->isZero());
        self::assertSame('1', (string) Natural::parse('1000000000000000001')->subtract(Natural::parse('1000000000000000000')));
        self::assertSame('5', (string) Natural::of(5)->subtract(Natural::zero()));
    }

    public function testALargerNumberIsNotTakenAway(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('6 cannot be taken from 5: a natural number stays zero or more.');

        Natural::of(5)->subtract(Natural::of(6));
    }

    public function testMultiplicationSpansLimbs(): void
    {
        self::assertSame('999999998000000001', (string) Natural::of(999_999_999)->multiply(Natural::of(999_999_999)));
        self::assertSame('121932631137021795223746380111126352690', (string) Natural::parse('12345678901234567890')->multiply(Natural::parse('9876543210987654321')));
        self::assertTrue(Natural::zero()->multiply(Natural::of(5))->isZero());
        self::assertTrue(Natural::of(5)->multiply(Natural::zero())->isZero());
        self::assertSame('1000000000000000000000000000', (string) Natural::of(1_000_000_000)->multiply(Natural::parse('1000000000000000000')));
    }

    public function testPowers(): void
    {
        self::assertSame('1', (string) Natural::of(6)->power(0));
        self::assertSame('6', (string) Natural::of(6)->power(1));
        self::assertSame('1296', (string) Natural::of(6)->power(4));
        self::assertSame('221073919720733357899776', (string) Natural::of(6)->power(30));
        self::assertSame('2187', (string) Natural::of(3)->power(7));
        self::assertSame('0', (string) Natural::zero()->power(3));
        self::assertSame('1', (string) Natural::zero()->power(0));
    }

    public function testANegativePowerIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A natural number is raised to a power of zero or more; -1 is not.');

        Natural::of(2)->power(-1);
    }

    #[DataProvider('divisions')]
    public function testDivisionGivesTheQuotientAndTheRemainder(string $dividend, string $divisor, string $quotient, string $remainder): void
    {
        [$q, $r] = Natural::parse($dividend)->divide(Natural::parse($divisor));

        self::assertSame([$quotient, $remainder], [(string) $q, (string) $r]);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function divisions(): iterable
    {
        yield 'small' => ['17', '5', '3', '2'];
        yield 'exact' => ['1296', '36', '36', '0'];
        yield 'smaller dividend' => ['3', '7', '0', '3'];
        yield 'dividend two limbs shorter' => ['1000000000000000005', '1000000000000000000000000000000000000', '0', '1000000000000000005'];
        yield 'zero dividend' => ['0', '7', '0', '0'];
        yield 'by one' => ['123456789012345678901234567890', '1', '123456789012345678901234567890', '0'];
        yield 'large by small' => ['221073919720733357899776', '6', '36845653286788892983296', '0'];
        yield 'large by large' => ['121932631137021795223746380111126352691', '9876543210987654321', '12345678901234567890', '1'];
        yield 'limb boundary' => ['1000000000000000000', '999999999', '1000000001', '1'];
        yield 'top digit' => ['999999999999999999999999999', '1000000000', '999999999999999999', '999999999'];
        yield 'equal' => ['987654321987654321', '987654321987654321', '1', '0'];
        yield 'long quotient' => ['100000000000000000000000000000000000000000000000000', '3', '33333333333333333333333333333333333333333333333333', '1'];
        yield 'long divisor' => ['340282366920938463463374607431768211456', '18446744073709551616', '18446744073709551616', '0'];
        yield 'near half' => ['999999999999999999999999999999', '500000000000000000000000000000', '1', '499999999999999999999999999999'];
    }

    public function testTheSizeIsTheNumberOfLimbs(): void
    {
        self::assertSame(1, Natural::zero()->size());
        self::assertSame(1, Natural::of(999_999_999)->size());
        self::assertSame(2, Natural::of(1_000_000_000)->size());
        self::assertSame(96, Natural::of(6)->power(1_100)->size());
    }

    /**
     * Euclid's algorithm on numbers of 96 limbs: the digits of a quotient start where the divisor ends, so a division
     * of two numbers of the same size finds one digit, not 96.
     */
    public function testTheGreatestCommonDivisorOfLargeNumbersIsQuick(): void
    {
        // 1 - (1 - 1/6^11)^100, the probability that one of a hundred exploding dice was cut at its depth.
        $denominator = Natural::of(6)->power(1_100);
        $numerator = $denominator->subtract(Natural::of(6)->power(11)->subtract(Natural::one())->power(100));
        $start = hrtime(true);

        self::assertSame('1', (string) $numerator->gcd($denominator));
        self::assertSame('6', (string) Natural::of(6)->power(95)->gcd(Natural::of(6)->power(96)->add(Natural::of(6))));
        self::assertLessThan(5_000_000_000, hrtime(true) - $start);
    }

    public function testDivisionByZeroIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A natural number is not divided by zero.');

        Natural::of(5)->divide(Natural::zero());
    }

    public function testTheDifferenceSaysWhichIsSmaller(): void
    {
        self::assertSame(['3', true], $this->difference(Natural::of(5), Natural::of(8)));
        self::assertSame(['3', false], $this->difference(Natural::of(8), Natural::of(5)));
        self::assertSame(['0', false], $this->difference(Natural::of(5), Natural::of(5)));
    }

    public function testGreatestCommonDivisor(): void
    {
        self::assertSame('4', (string) Natural::of(16)->gcd(Natural::of(36)));
        self::assertSame('4', (string) Natural::of(36)->gcd(Natural::of(16)));
        self::assertSame('36', (string) Natural::zero()->gcd(Natural::of(36)));
        self::assertSame('36', (string) Natural::of(36)->gcd(Natural::zero()));
        self::assertSame('1', (string) Natural::of(17)->gcd(Natural::of(5)));
        self::assertSame('1296', (string) Natural::of(6)->power(30)->gcd(Natural::of(1296)));
    }

    public function testComparison(): void
    {
        self::assertSame(0, Natural::of(5)->compare(Natural::of(5)));
        self::assertSame(-1, Natural::of(4)->compare(Natural::of(5)));
        self::assertSame(1, Natural::of(6)->compare(Natural::of(5)));
        self::assertSame(1, Natural::of(1_000_000_000)->compare(Natural::of(999_999_999)));
        self::assertSame(-1, Natural::of(999_999_999)->compare(Natural::of(1_000_000_000)));
        self::assertSame(-1, Natural::parse('1000000001000000000')->compare(Natural::parse('1000000002000000000')));
        self::assertSame(1, Natural::parse('2000000001')->compare(Natural::parse('1000000002')));
        self::assertSame(-1, Natural::parse('1000000001')->compare(Natural::parse('2000000000')));
        self::assertSame(0, Natural::zero()->compare(Natural::zero()));
        self::assertTrue(Natural::of(12)->equals(Natural::parse('012')));
        self::assertFalse(Natural::of(12)->equals(Natural::of(13)));
    }

    public function testAnIntegerIsGivenWhenItFits(): void
    {
        self::assertSame(0, Natural::zero()->toInt());
        self::assertSame(1296, Natural::of(1296)->toInt());
        self::assertSame(\PHP_INT_MAX, Natural::of(\PHP_INT_MAX)->toInt());
        self::assertSame(999_999_999_999_999_999, Natural::parse('999999999999999999')->toInt());
        self::assertSame(1_000_000_000_000_000_000, Natural::parse('1000000000000000000')->toInt());
        self::assertNull(Natural::of(\PHP_INT_MAX)->add(Natural::one())->toInt());
        self::assertNull(Natural::parse('9999999999999999999')->toInt());
        self::assertNull(Natural::parse('10000000000000000000')->toInt());
    }

    public function testTheRatioOfTwoNumbersHoldsAFloatsPrecisionAtAnySize(): void
    {
        self::assertSame(0.25, Natural::ratio(Natural::of(9), Natural::of(36)));
        self::assertSame(0.0, Natural::ratio(Natural::zero(), Natural::of(36)));
        self::assertSame(0.0, Natural::ratio(Natural::zero(), Natural::zero()));
        self::assertSame(\INF, Natural::ratio(Natural::one(), Natural::zero()));
        self::assertEqualsWithDelta(1 / 3, Natural::ratio(Natural::of(10)->power(400), Natural::of(3)->multiply(Natural::of(10)->power(400))), 1e-15);
        self::assertEqualsWithDelta(1e9, Natural::ratio(Natural::of(10)->power(409), Natural::of(10)->power(400)), 1e-3);
        self::assertEqualsWithDelta(1.234567890123, Natural::ratio(Natural::parse('1234567890123456789012345678901234567890'), Natural::parse('1000000000000000000000000000000000000000')), 1e-12);
        self::assertSame(2.0, Natural::ratio(Natural::parse('2000000000'), Natural::parse('1000000000')));
        self::assertSame(1e18, Natural::ratio(Natural::parse('1000000000000000000'), Natural::one()));
        self::assertSame(1e-18, Natural::ratio(Natural::one(), Natural::parse('1000000000000000000')));
        self::assertSame(1_000_000_005.0, Natural::ratio(Natural::of(1_000_000_005), Natural::one()));
        self::assertEqualsWithDelta(1.4285714285714e39, Natural::ratio(Natural::of(10)->power(40), Natural::of(7)), 1e26);
        self::assertEqualsWithDelta(7e-40, Natural::ratio(Natural::of(7), Natural::of(10)->power(40)), 1e-53);
        self::assertEqualsWithDelta(1.2345678901234567e10, Natural::ratio(Natural::parse('1234567890123456789012345678901234567'), Natural::parse('100000000000000000000000000')), 1e-3);
    }

    /**
     * @return array{string, bool}
     */
    private function difference(Natural $first, Natural $second): array
    {
        [$difference, $smaller] = $first->difference($second);

        return [(string) $difference, $smaller];
    }
}
