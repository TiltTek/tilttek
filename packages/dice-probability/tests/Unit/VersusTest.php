<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Distribution;
use Tilttek\DiceProbability\Estimate;
use Tilttek\DiceProbability\EstimateReason;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbability\Settings;
use Tilttek\DiceProbability\Versus;

#[CoversClass(Versus::class)]
final class VersusTest extends TestCase
{
    public function testAnAttackAgainstADefence(): void
    {
        $versus = new Probabilities()->compare(new DiceRoller()->parse('1d20+5'), new DiceRoller()->parse('2d10+4'));

        self::assertSame('1000/2000', (string) $versus->first);
        self::assertSame('100/2000', (string) $versus->tie);
        self::assertSame('900/2000', (string) $versus->second);
        self::assertFalse($versus->isEstimated());
        self::assertNull($versus->margin());
        self::assertTrue($versus->remaining()->isZero());
        self::assertSame('20', (string) $versus->firstDistribution->total);
        self::assertSame('100', (string) $versus->secondDistribution->total);
    }

    public function testValuesThatDoNotMeet(): void
    {
        $low = new Distribution([1 => Natural::one(), 2 => Natural::one()]);
        $high = new Distribution([5 => Natural::one(), 7 => Natural::one()]);
        $mixed = new Distribution([0 => Natural::one(), 3 => Natural::one(), 6 => Natural::one()]);

        self::assertSame(['0/4', '0/4', '4/4'], $this->shares(new Versus($low, $high)));
        self::assertSame(['4/4', '0/4', '0/4'], $this->shares(new Versus($high, $low)));
        self::assertSame(['5/6', '0/6', '1/6'], $this->shares(new Versus($high, $mixed)));
        self::assertSame(['1/6', '0/6', '5/6'], $this->shares(new Versus($mixed, $high)));
        self::assertSame(['3/9', '3/9', '3/9'], $this->shares(new Versus($mixed, $mixed)));
    }

    public function testAnEstimateOnEitherSideMakesTheComparisonEstimated(): void
    {
        $exact = new Distribution([1 => Natural::one()], Fraction::of(1, 4));
        $estimated = new Distribution([1 => Natural::one()], Fraction::of(1, 3), new Estimate(10_000, 0, EstimateReason::TooLarge));
        $margin = new Estimate(10_000, 0, EstimateReason::TooLarge)->margin();

        self::assertTrue(new Versus($exact, $estimated)->isEstimated());
        self::assertTrue(new Versus($estimated, $exact)->isEstimated());
        self::assertSame($margin, new Versus($exact, $estimated)->margin());
        self::assertSame($margin, new Versus($estimated, $exact)->margin());
        self::assertSame(2 * $margin, new Versus($estimated, $estimated)->margin());
        self::assertSame('1/2', (string) new Versus($exact, $estimated)->remaining());
    }

    public function testTheRoundingAppliesToBoth(): void
    {
        $versus = new Probabilities(settings: new Settings())->compare(new DiceRoller()->parse('1d6/4'), new DiceRoller()->parse('1d6/4'), RoundingMode::PositiveInfinity);

        self::assertSame('20/36', (string) $versus->tie);
    }

    /**
     * @return list<string>
     */
    private function shares(Versus $versus): array
    {
        return [(string) $versus->first, (string) $versus->tie, (string) $versus->second];
    }
}
