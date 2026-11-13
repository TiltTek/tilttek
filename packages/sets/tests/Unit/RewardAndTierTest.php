<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\ArrayInput;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Reward;
use Tilttek\Sets\Tier;

#[CoversClass(Reward::class)]
#[CoversClass(Tier::class)]
#[CoversClass(ArrayInput::class)]
final class RewardAndTierTest extends TestCase
{
    public function testARewardIsAnOpaqueReference(): void
    {
        $reward = new Reward('badge', 'Gold/Dice:1');

        self::assertSame(['kind' => 'badge', 'reference' => 'Gold/Dice:1', 'quantity' => 1], $reward->toArray());
        self::assertEquals($reward, Reward::fromArray($reward->toArray()));
        self::assertEquals(new Reward('points', 'xp', 50), Reward::fromArray(['kind' => 'points', 'reference' => 'xp', 'quantity' => 50]));
        self::assertSame(1, Reward::fromArray(['kind' => 'points', 'reference' => 'xp'])->quantity);
        self::assertSame(Reward::MAX_QUANTITY, new Reward('points', 'xp', Reward::MAX_QUANTITY)->quantity);
        self::assertSame(255, \strlen(new Reward('item', str_repeat('r', 255))->reference));
    }

    /**
     * @return iterable<string, array{string, string, int, string}>
     */
    public static function refusedRewards(): iterable
    {
        $reference = 'The reference of a reward is 1 to 255 bytes without whitespace or control characters.';

        yield 'kind' => ['Points', 'xp', 1, 'The code of a reward kind is'];
        yield 'empty reference' => ['points', '', 1, $reference];
        yield 'long reference' => ['points', str_repeat('r', 256), 1, $reference];
        yield 'space' => ['points', 'x p', 1, $reference];
        yield 'control' => ['points', "x\x7F", 1, $reference];
        yield 'nul' => ['points', "x\x00", 1, $reference];
        yield 'none' => ['points', 'xp', 0, 'A reward gives 1 to 1000000000; this one gives 0.'];
        yield 'too many' => ['points', 'xp', Reward::MAX_QUANTITY + 1, 'A reward gives 1 to 1000000000; this one gives 1000000001.'];
    }

    #[DataProvider('refusedRewards')]
    public function testARewardOutOfBoundsIsRefused(string $kind, string $reference, int $quantity, string $message): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Reward($kind, $reference, $quantity);
    }

    public function testATierRequiresEveryMemberUnlessItSaysHowMany(): void
    {
        $tier = new Tier('complete');

        self::assertSame(1, $tier->level);
        self::assertNull($tier->required);
        self::assertSame(20, $tier->requiredOf(20));
        self::assertSame(5, new Tier('bronze', required: 5)->requiredOf(20));
        self::assertSame(Tier::MAX_LEVEL, new Tier('top', Tier::MAX_LEVEL)->level);
        self::assertSame(1, new Tier('one', required: 1)->required);
    }

    public function testATierWritesAndReadsBack(): void
    {
        $tier = new Tier('gold', 3, 10, [new Reward('badge', 'gold')]);

        self::assertSame(
            ['code' => 'gold', 'level' => 3, 'required' => 10, 'rewards' => [['kind' => 'badge', 'reference' => 'gold', 'quantity' => 1]]],
            $tier->toArray(),
        );
        self::assertEquals($tier, Tier::fromArray($tier->toArray()));
        self::assertEquals(new Tier('plain'), Tier::fromArray(['code' => 'plain']));
    }

    /**
     * @return iterable<string, array{string, int, ?int, int, string}>
     */
    public static function refusedTiers(): iterable
    {
        yield 'level 0' => ['t', 0, null, 0, 'The level of the tier "t" is 1 to 1000; 0 is not.'];
        yield 'level too high' => ['t', Tier::MAX_LEVEL + 1, null, 0, 'The level of the tier "t" is 1 to 1000; 1001 is not.'];
        yield 'nothing required' => ['t', 1, 0, 0, 'The tier "t" requires at least one member.'];
        yield 'too many rewards' => ['t', 1, null, Tier::MAX_REWARDS + 1, 'The tier "t" declares at most 20 rewards.'];
        yield 'code' => ['T', 1, null, 0, 'The code of a tier is'];
    }

    #[DataProvider('refusedTiers')]
    public function testATierOutOfBoundsIsRefused(string $code, int $level, ?int $required, int $rewards, string $message): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Tier($code, $level, $required, array_fill(0, $rewards, new Reward('points', 'xp')));
    }

    public function testTwentyRewardsAreAccepted(): void
    {
        $tier = new Tier('t', rewards: array_fill(0, 20, new Reward('points', 'xp')));

        self::assertCount(20, $tier->rewards);
        self::assertCount(20, Tier::fromArray($tier->toArray())->rewards);
    }

    /**
     * @return iterable<string, array{mixed, string}>
     */
    public static function unreadableTiers(): iterable
    {
        yield 'not an array' => ['gold', 'A tier is an object of keys.'];
        yield 'a list' => [['gold'], 'A tier is an object of keys.'];
        yield 'empty' => [[], 'A tier needs its "code".'];
        yield 'unknown key' => [['code' => 'gold', 'colour' => 'yellow'], 'A tier has no key "colour".'];
        yield 'numeric key' => [['code' => 'gold', 3 => 'x'], 'A tier has no key "3".'];
        yield 'code not a string' => [['code' => 3], 'The "code" of a tier is a string.'];
        yield 'level not an integer' => [['code' => 'gold', 'level' => '2'], 'The "level" of a tier is an integer.'];
        yield 'required not an integer' => [['code' => 'gold', 'required' => 2.0], 'The "required" of a tier is an integer.'];
        yield 'rewards not a list' => [['code' => 'gold', 'rewards' => ['a' => 1]], 'The "rewards" of a tier is a list.'];
        yield 'rewards not an array' => [['code' => 'gold', 'rewards' => 'x'], 'The "rewards" of a tier is a list.'];
        yield 'too many rewards' => [['code' => 'gold', 'rewards' => array_fill(0, 21, ['kind' => 'points', 'reference' => 'xp'])], 'A tier has at most 20 "rewards".'];
        yield 'reward unreadable' => [['code' => 'gold', 'rewards' => [['kind' => 'points']]], 'A reward needs its "reference".'];
        yield 'long key' => [['code' => 'gold', str_repeat('k', 100) => 1], 'A tier has no key "'.str_repeat('k', 64).'".'];
    }

    #[DataProvider('unreadableTiers')]
    public function testATierThatDoesNotReadIsRefused(mixed $data, string $message): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains($message);

        Tier::fromArray($data);
    }

    public function testARewardOfTheWrongTypesIsRefused(): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('The "quantity" of a reward is an integer.');

        Reward::fromArray(['kind' => 'points', 'reference' => 'xp', 'quantity' => null]);
    }
}
