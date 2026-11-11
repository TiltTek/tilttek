<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit\Exact;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;

#[CoversClass(Fraction::class)]
final class FractionTest extends TestCase
{
    public function testAFractionKeepsItsDenominator(): void
    {
        self::assertSame('16/36', (string) Fraction::of(16, 36));
        self::assertSame('-7/2', (string) Fraction::of(-7, 2));
        self::assertSame('-7/2', (string) Fraction::of(7, -2));
        self::assertSame('7/2', (string) Fraction::of(-7, -2));
        self::assertSame('3/1', (string) Fraction::of(3));
        self::assertSame('0/1', (string) Fraction::zero());
        self::assertSame('1/1', (string) Fraction::one());
        self::assertSame('0/5', (string) Fraction::of(0, -5));
        self::assertSame('2/2', (string) Fraction::of(2, 2));
        self::assertSame('2/2', (string) Fraction::of(-2, -2));
        self::assertSame('-2/2', (string) Fraction::of(-2, 2));
        self::assertFalse(Fraction::of(0, -5)->negative);
        self::assertFalse(new Fraction(Natural::zero(), Natural::one(), true)->negative);
        self::assertTrue(new Fraction(Natural::one(), Natural::one(), true)->negative);
        self::assertSame('16', (string) Fraction::of(16, 36)->numerator);
        self::assertSame('36', (string) Fraction::of(16, 36)->denominator);
    }

    public function testAZeroDenominatorIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A fraction has a denominator other than zero.');

        Fraction::of(1, 0);
    }

    public function testAZeroNaturalDenominatorIsRefused(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A fraction has a denominator other than zero.');

        new Fraction(Natural::one(), Natural::zero());
    }

    public function testArithmetic(): void
    {
        self::assertSame('5/6', (string) Fraction::of(1, 2)->add(Fraction::of(1, 3))->reduced());
        self::assertSame('1/6', (string) Fraction::of(1, 2)->subtract(Fraction::of(1, 3))->reduced());
        self::assertSame('-1/6', (string) Fraction::of(1, 3)->subtract(Fraction::of(1, 2))->reduced());
        self::assertSame('-5/6', (string) Fraction::of(-1, 2)->add(Fraction::of(-1, 3))->reduced());
        self::assertSame('1/6', (string) Fraction::of(-1, 3)->add(Fraction::of(1, 2))->reduced());
        self::assertSame('-1/6', (string) Fraction::of(1, 3)->add(Fraction::of(-1, 2))->reduced());
        self::assertSame('1/6', (string) Fraction::of(1, 2)->add(Fraction::of(-1, 3))->reduced());
        self::assertTrue(Fraction::of(1, 2)->add(Fraction::of(-1, 2))->isZero());
        self::assertFalse(Fraction::of(1, 2)->add(Fraction::of(-1, 2))->negative);
        self::assertTrue(Fraction::of(-1, 2)->add(Fraction::of(1, 2))->isZero());
        self::assertSame('-1/4', (string) Fraction::of(-1, 2)->add(Fraction::of(1, 4))->reduced());
        self::assertSame('1/4', (string) Fraction::of(1, 2)->add(Fraction::of(-1, 4))->reduced());
        self::assertSame('-1/6', (string) Fraction::of(-1, 2)->multiply(Fraction::of(1, 3)));
        self::assertSame('1/6', (string) Fraction::of(-1, 2)->multiply(Fraction::of(-1, 3)));
        self::assertSame('-1/2', (string) Fraction::of(1, 2)->negate());
        self::assertSame('1/2', (string) Fraction::of(-1, 2)->negate());
    }

    public function testPowers(): void
    {
        self::assertSame('1/1', (string) Fraction::of(-2, 3)->power(0));
        self::assertSame('-8/27', (string) Fraction::of(-2, 3)->power(3));
        self::assertSame('4/9', (string) Fraction::of(-2, 3)->power(2));
        self::assertSame('25/36', (string) Fraction::of(5, 6)->power(2));
    }

    public function testTheComplementKeepsTheDenominator(): void
    {
        self::assertSame('20/36', (string) Fraction::of(16, 36)->complement());
        self::assertSame('2/3', (string) Fraction::of(1, 3)->complement());
        self::assertSame('0/1', (string) Fraction::one()->complement());
        self::assertSame('1/1', (string) Fraction::zero()->complement());
    }

    public function testComparison(): void
    {
        self::assertSame(0, Fraction::of(16, 36)->compare(Fraction::of(4, 9)));
        self::assertSame(-1, Fraction::of(1, 3)->compare(Fraction::of(1, 2)));
        self::assertSame(1, Fraction::of(1, 2)->compare(Fraction::of(1, 3)));
        self::assertSame(-1, Fraction::of(-1, 2)->compare(Fraction::of(1, 3)));
        self::assertTrue(Fraction::of(16, 36)->equals(Fraction::of(4, 9)));
        self::assertFalse(Fraction::of(16, 36)->equals(Fraction::of(5, 9)));
    }

    public function testLowestTerms(): void
    {
        self::assertSame('4/9', (string) Fraction::of(16, 36)->reduced());
        self::assertSame('-4/9', (string) Fraction::of(-16, 36)->reduced());
        self::assertSame('0/1', (string) Fraction::of(0, 36)->reduced());
        self::assertSame('7/1', (string) Fraction::of(14, 2)->reduced());
    }

    public function testAnotherDenominatorWhenItIsAMultiple(): void
    {
        self::assertSame('16/36', (string) Fraction::of(4, 9)->over(Natural::of(36)));
        self::assertSame('-16/36', (string) Fraction::of(-8, 18)->over(Natural::of(36)));
        self::assertSame('4/9', (string) Fraction::of(4, 9)->over(Natural::of(10)));
        self::assertSame('0/36', (string) Fraction::of(0, 5)->over(Natural::of(36)));
    }

    public function testFloats(): void
    {
        self::assertEqualsWithDelta(0.4444444444, Fraction::of(16, 36)->toFloat(), 1e-9);
        self::assertSame(-3.5, Fraction::of(-7, 2)->toFloat());
        self::assertSame(0.0, Fraction::zero()->toFloat());
    }
}
