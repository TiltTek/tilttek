<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\ArrayInput;
use Tilttek\Sets\Evaluation;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Holdings;
use Tilttek\Sets\Reward;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Tier;

#[CoversClass(SetDefinition::class)]
#[CoversClass(Evaluation::class)]
#[CoversClass(ArrayInput::class)]
final class SetDefinitionTest extends TestCase
{
    private function dice(): SetDefinition
    {
        return new SetDefinition(
            'dice',
            2,
            ['d4', 'd6', 'd8', 'd10'],
            [new Tier('bronze', required: 2), new Tier('silver'), new Tier('gold', 2, rewards: [new Reward('badge', 'gold-dice')]), new Tier('platinum', 3)],
            [new Reward('points', 'xp', 50)],
        );
    }

    public function testTheEvaluationKeepsTheOrderOfTheSet(): void
    {
        $evaluation = $this->dice()->evaluate(Holdings::of(['d10', 'd6', 'd20']));

        self::assertSame(['d6', 'd10'], $evaluation->owned);
        self::assertSame(['d4', 'd8'], $evaluation->missing);
        self::assertSame(4, $evaluation->total());
        self::assertSame(2, $evaluation->ownedCount());
        self::assertSame(50, $evaluation->percent());
        self::assertFalse($evaluation->isComplete());
        self::assertSame(['bronze'], array_map(static fn (Tier $tier): string => $tier->code, $evaluation->tiersReached));
        self::assertTrue($evaluation->hasReached('bronze'));
        self::assertFalse($evaluation->hasReached('silver'));
        self::assertSame('silver', $evaluation->nextTier()?->code);
    }

    public function testThePercentageRoundsDownAndReaches100OnlyWhenComplete(): void
    {
        $set = new SetDefinition('three', 1, ['a', 'b', 'c']);

        self::assertSame(0, $set->evaluate(Holdings::none())->percent());
        self::assertSame(33, $set->evaluate(Holdings::of(['a']))->percent());
        self::assertSame(66, $set->evaluate(Holdings::of(['a', 'b']))->percent());
        self::assertSame(100, $set->evaluate(Holdings::of(['a', 'b', 'c']))->percent());
        self::assertTrue($set->evaluate(Holdings::of(['c', 'b', 'a']))->isComplete());
        self::assertNull($set->evaluate(Holdings::none())->nextTier());
    }

    public function testHigherLevelsReachTheTiersRedoneAtThoseLevels(): void
    {
        $set = $this->dice();
        $evaluation = $set->evaluate(Holdings::withLevels(['d4' => 2, 'd6' => 2, 'd8' => 3, 'd10' => 1]));

        self::assertTrue($evaluation->isComplete());
        self::assertSame(['bronze', 'silver'], array_map(static fn (Tier $tier): string => $tier->code, $evaluation->tiersReached));
        self::assertSame('gold', $evaluation->nextTier()?->code);

        $gold = $set->evaluate(Holdings::withLevels(['d4' => 2, 'd6' => 2, 'd8' => 3, 'd10' => 2]));
        self::assertSame(['bronze', 'silver', 'gold'], array_map(static fn (Tier $tier): string => $tier->code, $gold->tiersReached));
        self::assertSame('platinum', $gold->nextTier()?->code);

        $all = $set->evaluate(Holdings::withLevels(['d4' => 3, 'd6' => 3, 'd8' => 3, 'd10' => 3]));
        self::assertCount(4, $all->tiersReached);
        self::assertNull($all->nextTier());
        self::assertTrue($all->hasReached('platinum'));

        $two = $set->evaluate(Holdings::withLevels(['d4' => 2, 'd6' => 2]));
        self::assertSame(['bronze'], array_map(static fn (Tier $tier): string => $tier->code, $two->tiersReached));
    }

    public function testAMemberOwnedOnlyAtTheLevelOfATierCounts(): void
    {
        $set = new SetDefinition('s', 1, ['a', 'b'], [new Tier('shiny', 2, 1)]);

        self::assertTrue($set->evaluate(Holdings::withLevels(['a' => 2]))->hasReached('shiny'));
        self::assertFalse($set->evaluate(Holdings::withLevels(['a' => 1, 'b' => 1]))->hasReached('shiny'));
    }

    public function testATierIsFoundByItsCode(): void
    {
        self::assertSame('gold', $this->dice()->tier('gold')?->code);
        self::assertSame(3, $this->dice()->tier('platinum')?->level);
        self::assertNull($this->dice()->tier('diamond'));
    }

    public function testTheDefinitionWritesAndReadsBack(): void
    {
        $set = $this->dice();

        self::assertSame(
            [
                'code' => 'dice',
                'version' => 2,
                'members' => ['d4', 'd6', 'd8', 'd10'],
                'tiers' => [
                    ['code' => 'bronze', 'level' => 1, 'required' => 2, 'rewards' => []],
                    ['code' => 'silver', 'level' => 1, 'required' => null, 'rewards' => []],
                    ['code' => 'gold', 'level' => 2, 'required' => null, 'rewards' => [['kind' => 'badge', 'reference' => 'gold-dice', 'quantity' => 1]]],
                    ['code' => 'platinum', 'level' => 3, 'required' => null, 'rewards' => []],
                ],
                'rewards' => [['kind' => 'points', 'reference' => 'xp', 'quantity' => 50]],
            ],
            $set->toArray(),
        );
        self::assertTrue(SetDefinition::fromArray($set->toArray())->equals($set));
        self::assertTrue(SetDefinition::fromJson($set->toJson())->equals($set));
        self::assertStringContainsString('"reference":"a/b"', new SetDefinition('s', 1, ['a/b'], rewards: [new Reward('item', 'a/b')])->toJson());
        self::assertFalse(new SetDefinition('dice', 3, ['d4'])->equals(new SetDefinition('dice', 3, ['d6'])));
        self::assertEquals(new SetDefinition('plain', 1, ['a']), SetDefinition::fromArray(['code' => 'plain', 'version' => 1, 'members' => ['a']]));
    }

    public function testTheBoundsAreAccepted(): void
    {
        $members = array_map(static fn (int $member): string => 'm'.$member, range(1, SetDefinition::MAX_MEMBERS));
        $tiers = array_map(static fn (int $tier): Tier => new Tier('t'.$tier, $tier), range(1, SetDefinition::MAX_TIERS));
        $set = new SetDefinition('big', SetDefinition::MAX_VERSION, $members, $tiers, array_fill(0, SetDefinition::MAX_REWARDS, new Reward('points', 'xp')));

        self::assertCount(1000, $set->members);
        self::assertCount(20, $set->tiers);
        self::assertTrue(SetDefinition::fromArray($set->toArray())->equals($set));
        self::assertSame(1, new SetDefinition('small', 1, ['a'])->version);
        self::assertCount(1, new SetDefinition('one', 1, ['a'], [new Tier('all', required: 1)])->tiers);
    }

    /**
     * @return iterable<string, array{int, list<string>, list<Tier>, int, string}>
     */
    public static function refusedDefinitions(): iterable
    {
        yield 'version 0' => [0, ['a'], [], 0, 'The version of the set "s" is 1 to 1000000; 0 is not.'];
        yield 'version too high' => [SetDefinition::MAX_VERSION + 1, ['a'], [], 0, 'The version of the set "s" is 1 to 1000000; 1000001 is not.'];
        yield 'no member' => [1, [], [], 0, 'The set "s" has at least one member.'];
        yield 'too many members' => [1, array_map(static fn (int $member): string => 'm'.$member, range(0, SetDefinition::MAX_MEMBERS)), [], 0, 'The set "s" has at most 1000 members.'];
        yield 'twice' => [1, ['a', 'b', 'a'], [], 0, 'The set "s" names the member "a" twice.'];
        yield 'member' => [1, ['a b'], [], 0, 'A member of a set is'];
        yield 'too many tiers' => [1, ['a'], array_map(static fn (int $tier): Tier => new Tier('t'.$tier, $tier), range(1, SetDefinition::MAX_TIERS + 1)), 0, 'The set "s" has at most 20 tiers.'];
        yield 'too many rewards' => [1, ['a'], [], SetDefinition::MAX_REWARDS + 1, 'The set "s" declares at most 20 rewards.'];
        yield 'tier twice' => [1, ['a', 'b'], [new Tier('t', required: 1), new Tier('t')], 0, 'The set "s" names the tier "t" twice.'];
        yield 'too many required' => [1, ['a', 'b'], [new Tier('t', required: 3)], 0, 'The tier "t" requires 3 members; the set "s" has 2.'];
        yield 'same tier' => [1, ['a', 'b'], [new Tier('t'), new Tier('u')], 0, 'The tier "u" of the set "s" must be harder than the tier "t" before it'];
        yield 'same required' => [1, ['a', 'b'], [new Tier('t', required: 1), new Tier('u', required: 1)], 0, 'The tier "u" of the set "s" must be harder'];
        yield 'lower level' => [1, ['a', 'b'], [new Tier('t', 2), new Tier('u', 1)], 0, 'The tier "u" of the set "s" must be harder'];
        yield 'fewer required' => [1, ['a', 'b'], [new Tier('t', 1), new Tier('u', 2, 1)], 0, 'The tier "u" of the set "s" must be harder'];
    }

    /**
     * @param list<string> $members
     * @param list<Tier>   $tiers
     */
    #[DataProvider('refusedDefinitions')]
    public function testADefinitionOutOfBoundsIsRefused(int $version, array $members, array $tiers, int $rewards, string $message): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains($message);

        new SetDefinition('s', $version, $members, $tiers, array_fill(0, $rewards, new Reward('points', 'xp')));
    }

    public function testACodeOutOfShapeIsRefused(): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('The code of a set is');

        new SetDefinition('S', 1, ['a']);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function unreadableJson(): iterable
    {
        yield 'not JSON' => ['{', 'The JSON of a set does not read: Syntax error'];
        yield 'too deep' => ['{"code":"s","version":1,"members":["a"],"tiers":[{"code":"t","rewards":[{"kind":["points"],"reference":"xp"}]}]}', 'The JSON of a set does not read: Maximum stack depth exceeded'];
        yield 'a list' => ['[1]', 'A set is an object of keys.'];
        yield 'a string' => ['"s"', 'A set is an object of keys.'];
        yield 'an empty array' => ['[]', 'A set needs its "code".'];
        yield 'no version' => ['{"code":"s","members":["a"]}', 'A set needs its "version".'];
        yield 'no members' => ['{"code":"s","version":1}', 'A set needs its "members".'];
        yield 'empty members' => ['{"code":"s","version":1,"members":[]}', 'The set "s" has at least one member.'];
        yield 'member not a string' => ['{"code":"s","version":1,"members":[1]}', 'A member of a set is a string.'];
        yield 'version a string' => ['{"code":"s","version":"1","members":["a"]}', 'The "version" of a set is an integer.'];
        yield 'too many members' => ['{"code":"s","version":1,"members":['.implode(',', array_map(static fn (int $member): string => '"m'.$member.'"', range(0, 1000))).']}', 'A set has at most 1000 "members".'];
        yield 'tiers not a list' => ['{"code":"s","version":1,"members":["a"],"tiers":{"a":1}}', 'The "tiers" of a set is a list.'];
        yield 'rewards null' => ['{"code":"s","version":1,"members":["a"],"rewards":null}', 'The "rewards" of a set is a list.'];
        yield 'extra key' => ['{"code":"s","version":1,"members":["a"],"owner":"x"}', 'A set has no key "owner".'];
    }

    #[DataProvider('unreadableJson')]
    public function testJsonThatIsNotADefinitionIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains($message);

        SetDefinition::fromJson($json);
    }

    public function testJsonLongerThanTheBoundIsRefusedBeforeDecoding(): void
    {
        $json = '{"code":"s","version":1,"members":["a"]'.str_repeat(' ', SetDefinition::MAX_JSON_LENGTH).'}';

        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('The JSON of a set is at most 1048576 bytes.');

        SetDefinition::fromJson($json);
    }

    public function testJsonOfExactlyTheBoundIsRead(): void
    {
        $head = '{"code":"s","version":1,"members":["a"]';
        $json = $head.str_repeat(' ', SetDefinition::MAX_JSON_LENGTH - \strlen($head) - 1).'}';

        self::assertSame('s', SetDefinition::fromJson($json)->code);
    }

    public function testTheDepthOfAValidDefinitionIsRead(): void
    {
        $json = '{"code":"s","version":1,"members":["a"],"tiers":[{"code":"t","rewards":[{"kind":"points","reference":"xp"}]}]}';

        self::assertSame('xp', SetDefinition::fromJson($json)->tiers[0]->rewards[0]->reference);
    }
}
