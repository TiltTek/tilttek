<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\Internal\Text;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Tier;

#[CoversClass(Scale::class)]
#[CoversClass(Tier::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidRoster::class)]
final class ScaleTest extends TestCase
{
    public function testTheStandardScaleRunsThreeStepsEitherSide(): void
    {
        $scale = Scale::standard();

        self::assertSame(-3, $scale->min);
        self::assertSame(3, $scale->max);
        self::assertSame(0, $scale->start);
        self::assertSame(['hostile', 'unfriendly', 'neutral', 'friendly', 'allied'], array_map(static fn (Tier $tier): string => $tier->key, $scale->tiers));
        self::assertSame(['Hostile', 'Unfriendly', 'Neutral', 'Friendly', 'Allied'], array_map(static fn (Tier $tier): string => $tier->label, $scale->tiers));
        self::assertSame([-3, -2, 0, 1, 3], array_map(static fn (Tier $tier): int => $tier->from, $scale->tiers));
    }

    public function testEachStandingFallsInTheTierThatStartsAtOrBelowIt(): void
    {
        $scale = Scale::standard();
        $tiers = [];

        foreach (range(-3, 3) as $standing) {
            $tiers[] = $scale->tier($standing)->key;
        }

        self::assertSame(['hostile', 'unfriendly', 'unfriendly', 'neutral', 'friendly', 'friendly', 'allied'], $tiers);
    }

    public function testAScaleOfOneTierHoldsEverything(): void
    {
        $scale = new Scale(0, 10, 5, [new Tier('known', 'Known', 0)]);

        self::assertSame('known', $scale->tier(0)->key);
        self::assertSame('known', $scale->tier(10)->key);
    }

    public function testAStandingOffTheScaleHasNoTier(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The standing 4 is off the scale, from -3 to 3.');

        Scale::standard()->tier(4);
    }

    public function testAStandingBelowTheScaleHasNoTier(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The standing -4 is off the scale, from -3 to 3.');

        Scale::standard()->tier(-4);
    }

    public function testContainsAndClampKeepTheEnds(): void
    {
        $scale = Scale::standard();

        self::assertTrue($scale->contains(-3));
        self::assertTrue($scale->contains(3));
        self::assertFalse($scale->contains(-4));
        self::assertFalse($scale->contains(4));
        self::assertSame(3, $scale->clamp(9));
        self::assertSame(-3, $scale->clamp(-9));
        self::assertSame(2, $scale->clamp(2));
    }

    public function testTheWidestScaleIsAccepted(): void
    {
        $scale = new Scale(-100, 100, -100, [new Tier('low', 'Low', -100), new Tier('high', 'High', 100)]);

        self::assertSame('high', $scale->tier(100)->key);
        self::assertSame('low', $scale->tier(99)->key);
        self::assertSame(-100, $scale->start);
        self::assertSame(100, $scale->max);
        self::assertSame(1, new Scale(0, 1, 1, [new Tier('t', 'T', 0)])->start);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function badBounds(): iterable
    {
        $range = 'A scale runs from -100 or more to a higher end of 100 or less.';
        $start = 'The starting standing of a scale lies within it.';

        yield 'below the lowest' => [-101, 0, 0, $range];
        yield 'above the highest' => [0, 101, 0, $range];
        yield 'empty' => [0, 0, 0, $range];
        yield 'reversed' => [1, 0, 0, $range];
        yield 'start below' => [0, 3, -1, $start];
        yield 'start above' => [0, 3, 4, $start];
    }

    #[DataProvider('badBounds')]
    public function testBoundsOutOfTheirRangeAreRefused(int $min, int $max, int $start, string $message): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Scale($min, $max, $start, [new Tier('t', 'T', $min)]);
    }

    public function testAScaleHasAtLeastOneTier(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('A scale holds 1 to 12 tiers.');

        new Scale(0, 3, 0, []);
    }

    public function testAScaleHasAtMostTwelveTiers(): void
    {
        $tiers = array_map(static fn (int $from): Tier => new Tier('t'.$from, 'T', $from), range(0, 11));
        self::assertCount(12, new Scale(0, 20, 0, $tiers)->tiers);

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('A scale holds 1 to 12 tiers.');

        new Scale(0, 20, 0, [...$tiers, new Tier('t12', 'T', 12)]);
    }

    public function testTheFirstTierStartsAtTheLowestStanding(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The first tier of a scale starts at its lowest standing.');

        new Scale(0, 3, 0, [new Tier('t', 'T', 1)]);
    }

    public function testEachTierStartsAboveTheOneBefore(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The tier b starts above the tier before it and within the scale.');

        new Scale(0, 3, 0, [new Tier('a', 'A', 0), new Tier('b', 'B', 0)]);
    }

    public function testNoTierStartsPastTheHighestStanding(): void
    {
        self::assertSame('b', new Scale(0, 3, 0, [new Tier('a', 'A', 0), new Tier('b', 'B', 3)])->tier(3)->key);

        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The tier b starts above the tier before it and within the scale.');

        new Scale(0, 3, 0, [new Tier('a', 'A', 0), new Tier('b', 'B', 4)]);
    }

    public function testATierKeyComesOnce(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('The key a comes twice among the tiers.');

        new Scale(0, 3, 0, [new Tier('a', 'A', 0), new Tier('b', 'B', 1), new Tier('a', 'C', 2)]);
    }

    public function testATierHasAKeyAndALabelOfOneLine(): void
    {
        $tier = new Tier('friendly', '  Friendly  ', 1);

        self::assertSame('Friendly', $tier->label);
        self::assertSame(60, mb_strlen(new Tier('t', str_repeat('é', 60), 0)->label));

        foreach ([['1t', 'T', 'The key of a tier is 1 to 64'], ['t', '', 'The label of a tier is 1 to 60 characters.'], ['t', str_repeat('a', 61), 'The label of a tier is 1 to 60 characters.'], ['t', "a\nb", 'The label of a tier holds a control character or a direction override.']] as [$key, $label, $message]) {
            try {
                new Tier($key, $label, 0);
                self::fail('Refused: '.$key.' '.$label);
            } catch (InvalidRoster $refused) {
                self::assertStringStartsWith($message, $refused->getMessage());
            }
        }
    }

    public function testScalesAreEqualByTheirBoundsAndTiers(): void
    {
        $tiers = [new Tier('a', 'A', 0), new Tier('b', 'B', 2)];
        $scale = new Scale(0, 3, 1, $tiers);

        self::assertTrue($scale->equals(new Scale(0, 3, 1, $tiers)));
        self::assertFalse($scale->equals(new Scale(0, 4, 1, $tiers)));
        self::assertFalse($scale->equals(new Scale(-1, 3, 1, [new Tier('a', 'A', -1), new Tier('b', 'B', 2)])));
        self::assertFalse($scale->equals(new Scale(0, 3, 2, $tiers)));
        self::assertFalse($scale->equals(new Scale(0, 3, 1, [new Tier('a', 'A', 0)])));
        self::assertFalse($scale->equals(new Scale(0, 3, 1, [new Tier('a', 'A', 0), new Tier('b', 'B', 1)])));
        self::assertFalse($scale->equals(new Scale(0, 3, 1, [new Tier('a', 'A', 0), new Tier('c', 'B', 2)])));
        self::assertFalse($scale->equals(new Scale(0, 3, 1, [new Tier('a', 'A', 0), new Tier('b', 'C', 2)])));
    }
}
