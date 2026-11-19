<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\Rounding;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\ProbabilityExtension;
use Twig\Environment;
use Twig\Loader\ArrayLoader;

#[CoversClass(ProbabilityExtension::class)]
final class ProbabilityExtensionTest extends TestCase
{
    public function testTheDistributionAndTheComparison(): void
    {
        $extension = $this->extension();

        self::assertSame('6/36', (string) $extension->distribution('2d6')->probability(7));
        self::assertSame([0, 1, 2], $extension->distribution('1d6/3')->values());
        self::assertSame([1, 2], $extension->distribution('1d6/3', rounding: 'ceil')->values());
        self::assertSame([1, 2], $extension->distribution('1W6/3', 'de', 'ceil')->values());
        self::assertSame('100/2000', (string) $extension->versus('1d20+5', '2d10+4')->tie);
        self::assertSame('20/36', (string) $extension->versus('1d6/4', '1d6/4', rounding: 'ceil')->tie);
        self::assertSame('18/36', (string) $extension->versus('1d6/4', '1d6/4')->tie);
        self::assertSame('20/36', (string) $extension->versus('1W6/4', '1W6/4', 'de', 'ceil')->tie);
    }

    public function testAFractionInItsLowestTermsWhenItIsShortEnough(): void
    {
        $extension = $this->extension();

        self::assertSame('4/9', $extension->fraction(Fraction::of(16, 36)));
        self::assertSame('1/100000000000', $extension->fraction(new Fraction(Natural::one(), Natural::parse('100000000000'))));
        self::assertNull($extension->fraction(new Fraction(Natural::one(), Natural::parse('1000000000000'))));
        self::assertSame('1/36', $extension->fraction(Fraction::of(1, 36), 2));
        self::assertNull($extension->fraction(Fraction::of(1, 360), 2));
        // A denominator of more than twice the digits is not reduced, even when its lowest terms would be short.
        self::assertSame('1/2', $extension->fraction(Fraction::of(500, 1_000), 2));
        self::assertNull($extension->fraction(Fraction::of(5_000, 10_000), 2));
        self::assertNull($extension->fraction(new Fraction(Natural::of(10)->power(30), Natural::of(10)->power(30)->multiply(Natural::of(3)))));
    }

    #[RequiresPhpExtension('gmp')]
    public function testLongTermsAreReducedByGmp(): void
    {
        // Two terms of twenty digits, whose lowest terms are 1/3.
        $long = new Fraction(Natural::of(10)->power(19), Natural::of(10)->power(19)->multiply(Natural::of(3)));
        $statistics = new DiceStatistics(new DiceRoller(), new Probabilities());

        self::assertSame('1/3', new ProbabilityExtension($statistics, gmp: true)->fraction($long));
        self::assertSame('1/3', new ProbabilityExtension($statistics)->fraction($long));
        self::assertSame('-1/2', new ProbabilityExtension($statistics, gmp: true)->fraction(new Fraction(Natural::of(10)->power(19), Natural::of(10)->power(19)->multiply(Natural::of(2)), negative: true)));
    }

    public function testWithoutGmpLongTermsAreNotReduced(): void
    {
        $long = new Fraction(Natural::of(10)->power(19), Natural::of(10)->power(19)->multiply(Natural::of(3)));
        $statistics = new DiceStatistics(new DiceRoller(), new Probabilities());

        self::assertNull(new ProbabilityExtension($statistics, gmp: false)->fraction($long));
        // Eighteen digits are reduced on native integers, with or without ext-gmp.
        $native = new Fraction(Natural::parse('250000000000000000'), Natural::parse('750000000000000000'));
        self::assertSame('1/3', new ProbabilityExtension($statistics, gmp: false)->fraction($native));
        self::assertSame('1/30000000000000000', new ProbabilityExtension($statistics, gmp: false)->fraction(new Fraction(Natural::parse('25'), Natural::parse('750000000000000000')), 18));
        self::assertNull(new ProbabilityExtension($statistics, gmp: false)->fraction(new Fraction(Natural::parse('1250000000000000000'), Natural::parse('25')), 10));
        self::assertSame('-1/2', new ProbabilityExtension($statistics, gmp: false)->fraction(new Fraction(Natural::of(3), Natural::of(6), negative: true)));
        self::assertSame('0/1', $this->extension()->fraction(Fraction::zero()));
        self::assertSame(18, ProbabilityExtension::NATIVE_DIGITS);
    }

    #[RequiresPhpExtension('gmp')]
    public function testTheDigitsAreBounded(): void
    {
        $extension = new ProbabilityExtension(new DiceStatistics(new DiceRoller(), new Probabilities()), gmp: true);

        self::assertSame('1/7', $extension->fraction(Fraction::of(1, 7), 0));
        self::assertNull($extension->fraction(Fraction::of(1, 70), 0));
        self::assertSame(60, ProbabilityExtension::MAX_FRACTION_DIGITS);
        self::assertSame('1/'.str_repeat('9', 60), $extension->fraction(new Fraction(Natural::one(), Natural::parse(str_repeat('9', 60))), 1_000));
        self::assertNull($extension->fraction(new Fraction(Natural::one(), Natural::parse(str_repeat('9', 61))), 1_000));

        // A term of a thousand digits is turned down by its size, before it is written out.
        $start = hrtime(true);
        self::assertNull($extension->fraction(new Fraction(Natural::of(10)->power(999), Natural::of(10)->power(1_000)), 60));
        self::assertNull($extension->fraction(new Fraction(Natural::of(10)->power(1_000), Natural::of(3)), 60));
        self::assertLessThan(0.1, (hrtime(true) - $start) / 1e9);
        // Nineteen and twenty-seven digits: ones a limb more, or a digit more, than twice the digits.
        self::assertNull($extension->fraction(new Fraction(Natural::one(), Natural::parse('1'.str_repeat('0', 18))), 9));
        self::assertSame('1/10', $extension->fraction(new Fraction(Natural::parse('1'.str_repeat('0', 16)), Natural::parse('1'.str_repeat('0', 17))), 9));
        self::assertNull($extension->fraction(new Fraction(Natural::parse('1'.str_repeat('0', 26)), Natural::parse('1'.str_repeat('0', 26))), 13));
    }

    #[RequiresPhpExtension('gmp')]
    public function testATermOfTheLongestLengthIsStillReducedByGmp(): void
    {
        $extension = new ProbabilityExtension(new DiceStatistics(new DiceRoller(), new Probabilities()), gmp: true);

        // 119 digits are within twice 60 digits, and span fourteen limbs.
        self::assertSame('1/3', $extension->fraction(new Fraction(Natural::of(10)->power(118), Natural::of(10)->power(118)->multiply(Natural::of(3))), 60));
    }

    public function testATermIsReadUpToTwiceTheDigitsAndNoFurther(): void
    {
        $extension = $this->extension();

        self::assertSame('10/1', $extension->fraction(new Fraction(Natural::of(10), Natural::one()), 1));
        self::assertNull($extension->fraction(new Fraction(Natural::of(100), Natural::one()), 1));
        self::assertNull($extension->fraction(new Fraction(Natural::of(1), Natural::of(100)), 1));
    }

    /**
     * The fractions of the longest table that took seconds to reduce: each now in a few microseconds.
     */
    public function testTheFractionsOfALongTableAreQuick(): void
    {
        $distribution = new DiceStatistics(new DiceRoller(), new Probabilities())->distribution('16d30');
        $extension = $this->extension();
        $start = hrtime(true);

        foreach ($distribution->outcomes() as $outcome) {
            $extension->fraction($outcome->probability);
            $extension->fraction($outcome->atLeast);
            $extension->fraction($outcome->atMost);
        }

        self::assertLessThan(1.0, (hrtime(true) - $start) / 1e9);
    }

    public function testTheComponentsOfTheKitAreNamedAfterItsSubdirectory(): void
    {
        self::assertSame('Card:Title', $this->extension()->kit('Card:Title'));
        self::assertSame('ui:Card:Title', new ProbabilityExtension(new DiceStatistics(new DiceRoller(), new Probabilities()), new KitComponents('ui'))->kit('Card:Title'));
    }

    public function testAnInvalidExpressionIsRefused(): void
    {
        $this->expectException(InvalidExpression::class);

        $this->extension()->probabilities(new Environment(new ArrayLoader()), '2x6');
    }

    private function extension(): ProbabilityExtension
    {
        return new ProbabilityExtension(new DiceStatistics(new DiceRoller(), new Probabilities(), Rounding::Floor));
    }
}
