<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Conditions\Effect\AppliedModifier;
use Tilttek\Conditions\Effect\EffectCalculator;
use Tilttek\Conditions\Effect\StatValue;
use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Modifier;
use Tilttek\Conditions\Rounding;

#[CoversClass(EffectCalculator::class)]
#[CoversClass(AppliedModifier::class)]
#[CoversClass(StatValue::class)]
#[CoversClass(Rounding::class)]
final class EffectCalculatorTest extends TestCase
{
    public function testAStatisticWithoutModifiersKeepsItsBase(): void
    {
        $values = new EffectCalculator()->compute(['speed' => 30, 'ac' => 12], [$this->applied(Modifier::add('other', 5))]);

        self::assertSame(['speed', 'ac'], array_keys($values));
        self::assertSame(30, $values['speed']->value);
        self::assertSame(30, $values['speed']->base);
        self::assertSame('speed', $values['speed']->stat);
        self::assertSame([], $values['speed']->contributions);
        self::assertSame(12, $values['ac']->value);
    }

    public function testOperationsRunInTheirOrder(): void
    {
        $add = $this->applied(Modifier::add('speed', 10));
        $half = $this->applied(Modifier::multiply('speed', 50));

        $value = new EffectCalculator()->compute(['speed' => 30], [$add, $half])['speed'];

        self::assertSame(25, $value->value);
        self::assertSame([$half, $add], $value->contributions);
    }

    public function testMultiplicationsRunOneAfterTheOtherEachRounded(): void
    {
        $values = new EffectCalculator(Rounding::Down)->compute(['hp' => 5], [
            $this->applied(Modifier::multiply('hp', 150)),
            $this->applied(Modifier::multiply('hp', 150)),
        ]);

        self::assertSame(10, $values['hp']->value);
    }

    public function testAdditionsWithoutTypeStack(): void
    {
        $values = new EffectCalculator()->compute(['attack' => 0], [
            $this->applied(Modifier::add('attack', 2)),
            $this->applied(Modifier::add('attack', 3)),
            $this->applied(Modifier::add('attack', -1)),
        ]);

        self::assertSame(4, $values['attack']->value);
        self::assertCount(3, $values['attack']->contributions);
    }

    public function testAdditionsOfOneTypeKeepTheHighestBonusAndTheLowestPenalty(): void
    {
        $small = $this->applied(Modifier::add('attack', 1, type: 'status'));
        $large = $this->applied(Modifier::add('attack', 3, type: 'status'));
        $tie = $this->applied(Modifier::add('attack', 3, type: 'status'));
        $minor = $this->applied(Modifier::add('attack', -1, type: 'status'));
        $major = $this->applied(Modifier::add('attack', -2, type: 'status'));
        $other = $this->applied(Modifier::add('attack', 2, type: 'circumstance'));
        $zero = $this->applied(Modifier::add('attack', 0, type: 'item'));
        $plain = $this->applied(Modifier::add('attack', 1));

        $value = new EffectCalculator()->compute(['attack' => 10], [$small, $large, $tie, $minor, $major, $other, $zero, $plain])['attack'];

        self::assertSame(14, $value->value);
        self::assertSame([$large, $major, $other, $plain], $value->contributions);
    }

    public function testAMultiplicationDividesByExactlyOneHundred(): void
    {
        $value = new EffectCalculator()->compute(['hp' => 99], [$this->applied(Modifier::multiply('hp', 100))])['hp'];

        self::assertSame(99, $value->value);
    }

    public function testAnAdditionThatDoesNotCountDoesNotHideTheNext(): void
    {
        $zero = $this->applied(Modifier::add('attack', 0, type: 'item'));
        $plain = $this->applied(Modifier::add('attack', 1));
        $large = $this->applied(Modifier::add('attack', 3, type: 'status'));

        $value = new EffectCalculator()->compute(['attack' => 10], [$zero, $plain, $large])['attack'];

        self::assertSame(14, $value->value);
        self::assertSame([$plain, $large], $value->contributions);
    }

    public function testTheHighestFloorRaisesAndCountsOnlyWhenItChangesTheValue(): void
    {
        $low = $this->applied(Modifier::upgrade('ac', 14));
        $high = $this->applied(Modifier::upgrade('ac', 16));
        $tie = $this->applied(Modifier::upgrade('ac', 16));

        $raised = new EffectCalculator()->compute(['ac' => 12], [$low, $high, $tie])['ac'];
        self::assertSame(16, $raised->value);
        self::assertSame([$high], $raised->contributions);

        $kept = new EffectCalculator()->compute(['ac' => 16], [$high])['ac'];
        self::assertSame(16, $kept->value);
        self::assertSame([], $kept->contributions);
    }

    public function testTheLowestCapLowersAndCountsOnlyWhenItChangesTheValue(): void
    {
        $high = $this->applied(Modifier::downgrade('speed', 10));
        $low = $this->applied(Modifier::downgrade('speed', 0));
        $tie = $this->applied(Modifier::downgrade('speed', 0));

        $lowered = new EffectCalculator()->compute(['speed' => 30], [$high, $low, $tie])['speed'];
        self::assertSame(0, $lowered->value);
        self::assertSame([$low], $lowered->contributions);

        $kept = new EffectCalculator()->compute(['speed' => 10], [$high])['speed'];
        self::assertSame(10, $kept->value);
        self::assertSame([], $kept->contributions);
    }

    public function testACapRunsAfterAFloor(): void
    {
        $values = new EffectCalculator()->compute(['speed' => 30], [
            $this->applied(Modifier::downgrade('speed', 5)),
            $this->applied(Modifier::upgrade('speed', 40)),
        ]);

        self::assertSame(5, $values['speed']->value);
    }

    public function testTheLastOverrideWins(): void
    {
        $first = $this->applied(Modifier::override('str', 19));
        $last = $this->applied(Modifier::override('str', 21));
        $add = $this->applied(Modifier::add('str', 4));

        $value = new EffectCalculator()->compute(['str' => 10], [$first, $last, $add])['str'];

        self::assertSame(21, $value->value);
        self::assertSame([$add, $last], $value->contributions);
    }

    public function testValuesStayWithinTheirBounds(): void
    {
        $calculator = new EffectCalculator();
        $grow = array_fill(0, 8, $this->applied(Modifier::multiply('hp', 1_000)));

        self::assertSame(EffectCalculator::MAX_RESULT, $calculator->compute(['hp' => EffectCalculator::MAX_BASE], $grow)['hp']->value);
        self::assertSame(-EffectCalculator::MAX_RESULT, $calculator->compute(['hp' => -EffectCalculator::MAX_BASE], $grow)['hp']->value);

        $add = array_fill(0, 3, new AppliedModifier(Modifier::add('hp', 1), 'c', null, 600_000_000_000_000));
        self::assertSame(EffectCalculator::MAX_RESULT, $calculator->compute(['hp' => 0], $add)['hp']->value);
        self::assertSame(EffectCalculator::MAX_BASE, $calculator->compute(['hp' => EffectCalculator::MAX_BASE], [])['hp']->value);
        self::assertSame(-EffectCalculator::MAX_BASE, $calculator->compute(['hp' => -EffectCalculator::MAX_BASE], [])['hp']->value);
    }

    public function testABaseValueOutOfItsBoundsIsRefused(): void
    {
        $this->expectException(InvalidCondition::class);
        new EffectCalculator()->compute(['hp' => EffectCalculator::MAX_BASE + 1], []);
    }

    public function testANegativeBaseValueOutOfItsBoundsIsRefused(): void
    {
        $this->expectException(InvalidCondition::class);
        new EffectCalculator()->compute(['hp' => -EffectCalculator::MAX_BASE - 1], []);
    }

    public function testAStatisticThatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectException(InvalidCondition::class);
        new EffectCalculator()->compute(['hit points' => 1], []);
    }

    public function testTooManyStatisticsAreRefused(): void
    {
        $base = [];
        for ($i = 0; $i < EffectCalculator::MAX_STATS; ++$i) {
            $base['s'.$i] = 1;
        }

        self::assertCount(EffectCalculator::MAX_STATS, new EffectCalculator()->compute($base, []));

        $base['extra'] = 1;
        $this->expectException(InvalidCondition::class);
        new EffectCalculator()->compute($base, []);
    }

    public function testAStatValueBecomesPlainData(): void
    {
        $value = new EffectCalculator()->compute(['ac' => 10], [new AppliedModifier(Modifier::add('ac', 1, true), 'shielded', 'i2', 3)])['ac'];

        self::assertSame([
            'stat' => 'ac',
            'base' => 10,
            'value' => 13,
            'contributions' => [['condition' => 'shielded', 'instance' => 'i2', 'operation' => 'add', 'amount' => 3]],
        ], $value->toArray());
    }

    /**
     * @return iterable<string, array{Rounding, int, int, int}>
     */
    public static function roundings(): iterable
    {
        foreach ([[7, 2], [-7, 2], [5, 2], [-5, 2], [6, 2], [-6, 2], [7, 3], [-7, 3], [8, 3], [-8, 3]] as [$dividend, $divisor]) {
            $exact = $dividend / $divisor;
            yield \sprintf('down %d/%d', $dividend, $divisor) => [Rounding::Down, $dividend, $divisor, (int) floor($exact)];
            yield \sprintf('up %d/%d', $dividend, $divisor) => [Rounding::Up, $dividend, $divisor, (int) ceil($exact)];
            yield \sprintf('half-up %d/%d', $dividend, $divisor) => [Rounding::HalfUp, $dividend, $divisor, (int) round($exact, 0, RoundingMode::HalfAwayFromZero)];
            yield \sprintf('toward-zero %d/%d', $dividend, $divisor) => [Rounding::TowardZero, $dividend, $divisor, (int) ($exact < 0 ? ceil($exact) : floor($exact))];
        }
    }

    #[DataProvider('roundings')]
    public function testEachRoundingDividesByItsRule(Rounding $rounding, int $dividend, int $divisor, int $expected): void
    {
        self::assertSame($expected, $rounding->divide($dividend, $divisor));
    }

    public function testTheRoundingOfTheCalculatorRoundsMultiplications(): void
    {
        $half = [$this->applied(Modifier::multiply('hp', 50))];

        self::assertSame(3, new EffectCalculator(Rounding::Down)->compute(['hp' => 7], $half)['hp']->value);
        self::assertSame(4, new EffectCalculator(Rounding::Up)->compute(['hp' => 7], $half)['hp']->value);
        self::assertSame(3, new EffectCalculator()->compute(['hp' => 7], $half)['hp']->value);
        self::assertSame(-4, new EffectCalculator()->compute(['hp' => -7], $half)['hp']->value);
    }

    private function applied(Modifier $modifier): AppliedModifier
    {
        return new AppliedModifier($modifier, 'condition', 'i1', $modifier->amount);
    }
}
