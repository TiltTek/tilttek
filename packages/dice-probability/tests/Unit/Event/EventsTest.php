<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Unit\Event;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\DiceProbability\Computation\Multisets;
use Tilttek\DiceProbability\Computation\Sampler;
use Tilttek\DiceProbability\EstimateReason;
use Tilttek\DiceProbability\Event\AllDifferent;
use Tilttek\DiceProbability\Event\AllEqual;
use Tilttek\DiceProbability\Event\AllMatch;
use Tilttek\DiceProbability\Event\AllOf;
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\Event;
use Tilttek\DiceProbability\Event\Faces;
use Tilttek\DiceProbability\Event\Not;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbability\Event\Straight;
use Tilttek\DiceProbability\Exception\CannotCompute;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbability\Settings;
use Tilttek\DiceProbability\Tests\Unit\Fixture\LoadedDie;

#[CoversClass(Faces::class)]
#[CoversClass(Count::class)]
#[CoversClass(OfAKind::class)]
#[CoversClass(Straight::class)]
#[CoversClass(AllEqual::class)]
#[CoversClass(AllDifferent::class)]
#[CoversClass(AllMatch::class)]
#[CoversClass(AnyOf::class)]
#[CoversClass(AllOf::class)]
#[CoversClass(Not::class)]
#[CoversClass(Multisets::class)]
#[CoversClass(Sampler::class)]
#[CoversClass(Probabilities::class)]
#[CoversClass(CannotCompute::class)]
final class EventsTest extends TestCase
{
    #[DataProvider('oneOrADouble')]
    public function testAtLeastAOneOrADouble(string $dice, string $probability): void
    {
        $chance = new Probabilities()->chance(new AnyOf(Count::atLeastOne(1), OfAKind::double()), new DiceRoller()->parse($dice));

        self::assertSame($probability, (string) $chance->probability);
        self::assertFalse($chance->isEstimated());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function oneOrADouble(): iterable
    {
        yield 'two dice' => ['2d6', '16/36'];
        yield 'three dice' => ['3d6', '156/216'];
        yield 'four dice' => ['4d6', '1176/1296'];
    }

    #[DataProvider('events')]
    public function testEventsOnTheFacesOfASet(Event $event, string $dice, string $probability): void
    {
        self::assertSame($probability, (string) new Probabilities()->chance($event, new DiceRoller()->parse($dice))->probability);
    }

    /**
     * @return iterable<string, array{Event, string, string}>
     */
    public static function events(): iterable
    {
        yield 'at least one 1' => [Count::atLeastOne(1), '4d6', '671/1296'];
        yield 'exactly two sixes' => [Count::exactly(2, 6), '3d6', '15/216'];
        yield 'at most one six' => [Count::atMost(1, 6), '3d6', '200/216'];
        yield 'at least two of 5 or more' => [Count::atLeast(2, new Comparison(Comparator::GreaterOrEqual, 5)), '3d6', '56/216'];
        yield 'no six' => [Count::exactly(0, 6), '2d6', '25/36'];
        yield 'a double' => [OfAKind::double(), '3d6', '96/216'];
        yield 'a triple' => [OfAKind::triple(), '3d6', '6/216'];
        yield 'four of a kind' => [new OfAKind(4), '5d6', '156/7776'];
        yield 'a straight of every die' => [new Straight(), '3d6', '24/216'];
        yield 'a straight of three' => [new Straight(3), '4d6', '360/1296'];
        yield 'all equal' => [new AllEqual(), '4d6', '6/1296'];
        yield 'all different' => [new AllDifferent(), '3d6', '120/216'];
        yield 'all above 3' => [new AllMatch(new Comparison(Comparator::Greater, 3)), '3d6', '27/216'];
        yield 'a double and no 1' => [new AllOf(OfAKind::double(), new Not(Count::atLeastOne(1))), '3d6', '65/216'];
        yield 'neither' => [new Not(new AnyOf(Count::atLeastOne(1), OfAKind::double())), '2d6', '20/36'];
        yield 'Fudge dice' => [Count::atLeast(2, 1), '3dF', '7/27'];
        yield 'weighted Fudge dice' => [Count::atLeastOne(0), '2dF.1', '32/36'];
        yield 'rerolled faces' => [Count::atLeastOne(1), '2d6r1', '0/25'];
        yield 'faces rerolled once' => [Count::atLeastOne(1), '1d6ro1', '1/36'];
        yield 'one die' => [new Straight(), '1d6', '0/6'];
        yield 'a straight of two dice' => [new Straight(), '2d6', '10/36'];
    }

    public function testTheFacesOfCustomDiceThatCarryValues(): void
    {
        $coin = new DieDefinition('coin', new Face('heads', 'Heads', 1), new Face('tails', 'Tails', 0));

        self::assertSame('7/8', (string) new Probabilities(new CustomDice($coin))->chance(Count::atLeastOne(1), new DiceRoller(customDice: new CustomDice($coin))->parse('3d{coin}'))->probability);
    }

    public function testDiceWhoseFacesTheModifiersChangeAreEstimated(): void
    {
        $chance = new Probabilities(settings: new Settings(samples: 400, seed: 3))->chance(Count::atLeastOne(1), new DiceRoller()->parse('4d6kh3'));

        self::assertTrue($chance->isEstimated());
        self::assertSame(EstimateReason::Unsupported, $chance->estimate?->reason);
        self::assertSame('400', (string) $chance->probability->denominator);
        // Of the kept dice only: a 1 is kept when all four dice are 1 or below 3 at most once... about 13 %.
        self::assertEqualsWithDelta(0.13, $chance->probability->toFloat(), 0.05);
    }

    #[DataProvider('changedFaces')]
    public function testEachModifierThatChangesTheFacesLeadsToAnEstimate(string $dice): void
    {
        self::assertTrue(new Probabilities(settings: new Settings(samples: 10))->chance(new AllEqual(), new DiceRoller()->parse($dice))->isEstimated());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function changedFaces(): iterable
    {
        yield 'unique' => ['3d6u'];
        yield 'explosion' => ['3d6!'];
        yield 'all the same' => ['3d6t'];
        yield 'floor' => ['3d6min2'];
        yield 'ceiling' => ['3d6max5'];
        yield 'transformation' => ['3d6t[1:6]'];
        yield 'filter' => ['3d6f[>1]'];
        yield 'selection' => ['3d6kh2'];
        yield 'successes' => ['3d6>=5'];
        yield 'occurrences' => ['3d6o2'];
        yield 'groups' => ['3d6g10'];
    }

    public function testDiceWhoseFacesAreNotKnownAreEstimated(): void
    {
        $chance = new Probabilities(settings: new Settings(samples: 20))->chance(new AllEqual(), new Pool(new Dice(3, new LoadedDie())));

        self::assertSame('20/20', (string) $chance->probability);
        self::assertSame(EstimateReason::Unsupported, $chance->estimate?->reason);
    }

    public function testTooManyMultisetsAreEstimated(): void
    {
        $settings = new Settings(workLimit: 100, samples: 1_000, seed: 9);
        $chance = new Probabilities(settings: $settings)->chance(Count::atLeastOne(1), new DiceRoller()->parse('4d6'));

        self::assertSame(EstimateReason::TooLarge, $chance->estimate?->reason);
        self::assertSame('1000', (string) $chance->probability->denominator);
        self::assertEqualsWithDelta(671 / 1296, $chance->probability->toFloat(), 0.05);
        self::assertSame((string) $chance->probability, (string) new Probabilities(settings: $settings)->chance(Count::atLeastOne(1), new DiceRoller()->parse('4d6'))->probability);
        self::assertFalse(new Probabilities(settings: new Settings(workLimit: 140))->chance(Count::atLeastOne(1), new DiceRoller()->parse('4d6'))->isEstimated());
        self::assertTrue(new Probabilities(settings: new Settings(workLimit: 130, samples: 10))->chance(Count::atLeastOne(1), new DiceRoller()->parse('4d6'))->isEstimated());
    }

    public function testAnEventIsJudgedOnASetOfDice(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('An event is judged on the faces of one set of dice, such as "4d6".');

        new Probabilities()->chance(new AllEqual(), new DiceRoller()->parse('2d6+1'));
    }

    public function testAnEventIsJudgedOnOneSet(): void
    {
        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('A distribution is that of one expression; the pool holds 2.');

        new Probabilities()->chance(new AllEqual(), new DiceRoller()->parse('2d6, 2d6'));
    }

    public function testTheSetStaysWithinTheLimits(): void
    {
        $this->expectException(LimitExceeded::class);

        new Probabilities(limits: new Limits(dice: 3))->chance(new AllEqual(), new DiceRoller()->parse('4d6'));
    }

    public function testFacesWithoutValuesAreNotJudged(): void
    {
        $symbols = new DieDefinition('symbols', new Face('star', 'Star'), new Face('moon', 'Moon'));

        $this->expectException(CannotCompute::class);
        $this->expectExceptionMessageIsOrContains('The faces of the die "symbols" carry no value: they have no distribution.');

        new Probabilities(new CustomDice($symbols))->chance(new AllEqual(), new DiceRoller()->parse('2d{symbols}'));
    }

    public function testTheFacesOfASet(): void
    {
        $faces = Faces::of([3, 1, 3, 4, 6, 3]);

        self::assertSame([1 => 1, 3 => 3, 4 => 1, 6 => 1], $faces->counts);
        self::assertSame(6, $faces->dice());
        self::assertSame(3, $faces->showing(3));
        self::assertSame(0, $faces->showing(2));
        self::assertSame(5, $faces->matching(new Comparison(Comparator::GreaterOrEqual, 3)));
        self::assertSame(3, $faces->mostOfAKind());
        self::assertSame(2, $faces->longestRun());
        self::assertSame(4, Faces::of([5, 2, 3, 4, 9])->longestRun());
        self::assertSame(3, Faces::of([1, 2, 3, 7, 8])->longestRun());
        self::assertSame(1, Faces::of([1, 3])->longestRun());
        self::assertSame(0, Faces::of([])->longestRun());
        self::assertSame(0, Faces::of([])->mostOfAKind());
        self::assertSame([2 => 1], new Faces([2 => 1, 5 => 0])->counts);
        self::assertSame(3, Faces::of([-1, 0, 1])->longestRun());
        self::assertSame(1, Faces::of([4])->longestRun());
        self::assertSame(2, Faces::of([1, 2, 5])->longestRun());
    }

    public function testACountIsNeverNegative(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains('A value is shown by zero dice or more; -1 dice show 4.');

        new Faces([4 => -1]);
    }

    public function testEventsSayWhatTheyMatch(): void
    {
        $faces = Faces::of([2, 2, 5]);

        self::assertTrue(Count::atLeastOne(5)->matches($faces));
        self::assertFalse(Count::atLeastOne(6)->matches($faces));
        self::assertTrue(Count::exactly(2, 2)->matches($faces));
        self::assertFalse(Count::exactly(1, 2)->matches($faces));
        self::assertTrue(Count::atMost(2, 2)->matches($faces));
        self::assertFalse(Count::atMost(1, 2)->matches($faces));
        self::assertTrue(Count::atLeast(0, 6)->matches($faces));
        self::assertSame(Comparator::GreaterOrEqual, Count::atLeastOne(1)->comparator);
        self::assertSame(1, Count::atLeastOne(1)->dice);
        self::assertTrue(OfAKind::double()->matches($faces));
        self::assertFalse(OfAKind::triple()->matches($faces));
        self::assertSame(2, OfAKind::double()->dice);
        self::assertSame(3, OfAKind::triple()->dice);
        self::assertFalse(new Straight(2)->matches($faces));
        self::assertTrue(new Straight(2)->matches(Faces::of([4, 5])));
        self::assertNull(new Straight()->length);
        self::assertFalse(new AllEqual()->matches($faces));
        self::assertTrue(new AllEqual()->matches(Faces::of([4, 4])));
        self::assertTrue(new AllEqual()->matches(Faces::of([])));
        self::assertFalse(new AllDifferent()->matches($faces));
        self::assertTrue(new AllDifferent()->matches(Faces::of([4, 5])));
        self::assertTrue(new AllMatch(new Comparison(Comparator::Greater, 1))->matches($faces));
        self::assertFalse(new AllMatch(new Comparison(Comparator::Greater, 2))->matches($faces));
        self::assertTrue(new AnyOf(Count::atLeastOne(6), Count::atLeastOne(5))->matches($faces));
        self::assertFalse(new AnyOf(Count::atLeastOne(6), Count::atLeastOne(4))->matches($faces));
        self::assertTrue(new AllOf(Count::atLeastOne(2), Count::atLeastOne(5))->matches($faces));
        self::assertFalse(new AllOf(Count::atLeastOne(2), Count::atLeastOne(6))->matches($faces));
        self::assertFalse(new AllOf(Count::atLeastOne(6), Count::atLeastOne(2))->matches($faces));
        self::assertTrue(new Not(Count::atLeastOne(6))->matches($faces));
        self::assertCount(2, new AnyOf(new AllEqual(), new AllDifferent())->events);
        self::assertCount(2, new AllOf(new AllEqual(), new AllDifferent())->events);
        self::assertTrue(new AnyOf(Count::atLeastOne(6), Count::atLeastOne(4), Count::atLeastOne(5))->matches($faces));
        self::assertFalse(new AllOf(Count::atLeastOne(2), Count::atLeastOne(5), Count::atLeastOne(6))->matches($faces));
        self::assertCount(3, new AnyOf(new AllEqual(), new AllDifferent(), new AllEqual())->events);
        self::assertSame([0, 1], array_keys(new AnyOf(event: new AllEqual(), other: new AllDifferent())->events));
        self::assertSame([0, 1], array_keys(new AllOf(event: new AllEqual(), other: new AllDifferent())->events));
    }

    #[DataProvider('invalidEvents')]
    public function testInvalidEventsAreRefused(callable $event, string $message): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIsOrContains($message);

        $event();
    }

    /**
     * @return iterable<string, array{callable, string}>
     */
    public static function invalidEvents(): iterable
    {
        yield 'negative count' => [static fn (): Count => Count::atLeast(-1, 6), 'An event counts zero dice or more; -1 is not.'];
        yield 'one of a kind' => [static fn (): OfAKind => new OfAKind(1), 'Dice of a kind are two or more; 1 is not.'];
        yield 'straight of one' => [static fn (): Straight => new Straight(1), 'A straight runs over two values or more; 1 is not.'];
    }
}
