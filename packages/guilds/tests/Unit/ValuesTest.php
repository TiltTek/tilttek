<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Guilds\Admission;
use Tilttek\Guilds\Contribution;
use Tilttek\Guilds\Exception\GuildRefused;
use Tilttek\Guilds\Exception\InvalidGuild;
use Tilttek\Guilds\Exception\UnknownGuild;
use Tilttek\Guilds\Goal;
use Tilttek\Guilds\GoalProgress;
use Tilttek\Guilds\Guild;
use Tilttek\Guilds\Limits;
use Tilttek\Guilds\Metadata;
use Tilttek\Guilds\Refusal;
use Tilttek\Guilds\Role;

#[CoversClass(Guild::class)]
#[CoversClass(Metadata::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Role::class)]
#[CoversClass(Goal::class)]
#[CoversClass(GoalProgress::class)]
#[CoversClass(Contribution::class)]
#[CoversClass(GuildRefused::class)]
#[CoversClass(InvalidGuild::class)]
#[CoversClass(UnknownGuild::class)]
final class ValuesTest extends TestCase
{
    public function testAGuildKeepsItsValuesAndChangesSome(): void
    {
        $guild = $this->guild('The Lantern Bearers', 2);
        $changed = $guild->with(admission: Admission::OnRequest, capacity: 1000);

        self::assertSame(['The Lantern Bearers', Admission::Open, 2], [$guild->name, $guild->admission, $guild->capacity]);
        self::assertSame(['The Lantern Bearers', Admission::OnRequest, 1000], [$changed->name, $changed->admission, $changed->capacity]);
        self::assertSame($guild->foundedAt, $changed->foundedAt);
        self::assertSame($guild->metadata, $changed->metadata);
        self::assertTrue($guild->reference->equals($changed->reference));

        $renamed = $guild->with(name: 'Torch Club', metadata: new Metadata(['motto' => 'Light']));
        self::assertSame(['Torch Club', Admission::Open, 2, 'Light'], [$renamed->name, $renamed->admission, $renamed->capacity, $renamed->metadata->get('motto')]);
    }

    public function testANameOfAHundredCharactersIsAccepted(): void
    {
        self::assertSame(str_repeat('é', 100), $this->guild(str_repeat('é', 100))->name);
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function invalidGuilds(): iterable
    {
        $name = 'A guild name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.';

        yield 'an empty name' => ['', 50, $name];
        yield 'a name too long' => [str_repeat('a', 101), 50, $name];
        yield 'a name with a control character' => ["Lan\terns", 50, $name];
        yield 'a name with a leading space' => [' Lanterns', 50, $name];
        yield 'a name with a trailing space' => ['Lanterns ', 50, $name];
        yield 'a name of invalid UTF-8' => ["Lan\xFFterns", 50, $name];
        yield 'a capacity of one' => ['Lanterns', 1, 'A guild holds 2 to 1000 members; not 1.'];
        yield 'a capacity too large' => ['Lanterns', 1001, 'A guild holds 2 to 1000 members; not 1001.'];
    }

    #[DataProvider('invalidGuilds')]
    public function testAGuildOutOfBoundsIsRefused(string $name, int $capacity, string $error): void
    {
        $this->expectException(InvalidGuild::class);
        $this->expectExceptionMessageIsOrContains($error);

        $this->guild($name, $capacity);
    }

    public function testMetadataIsSortedAndRead(): void
    {
        $entries = [];

        for ($i = 19; $i >= 0; --$i) {
            $entries['entry'.$i] = 'value '.$i;
        }

        $metadata = new Metadata($entries);

        self::assertCount(20, $metadata->entries);
        self::assertSame('', new Metadata(['x' => ''])->get('x'));
        self::assertSame('entry0', array_key_first($metadata->entries));
        self::assertSame('value 3', $metadata->get('entry3'));
        self::assertNull($metadata->get('missing'));
        self::assertSame(['a.b-c_9' => str_repeat('é', 250)], new Metadata(['a.b-c_9' => str_repeat('é', 250)])->entries);
    }

    /**
     * @return iterable<string, array{array<string, string>, string}>
     */
    public static function invalidMetadata(): iterable
    {
        $entries = [];

        for ($i = 0; $i < 21; ++$i) {
            $entries['entry'.$i] = 'value';
        }

        yield 'too many entries' => [$entries, 'A guild holds at most 20 metadata entries, not 21.'];
        yield 'a name with a capital' => [['Motto' => 'x'], 'A metadata entry is named by 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter; "Motto" is not.'];
        yield 'a name starting with a digit' => [['9motto' => 'x'], '"9motto" is not.'];
        yield 'a name too long' => [[str_repeat('a', 65) => 'x'], 'is not.'];
        yield 'a value too long' => [['motto' => str_repeat('a', 501)], 'The metadata entry "motto" holds at most 500 bytes of UTF-8 without control characters.'];
        yield 'a value with a control character' => [['motto' => "a\nb"], 'The metadata entry "motto"'];
        yield 'a value of invalid UTF-8' => [['motto' => "\xC3"], 'The metadata entry "motto"'];
        yield 'a value ending with a line break' => [['motto' => "a\n"], 'The metadata entry "motto"'];
        yield 'a name ending with a line break' => [["motto\n" => 'x'], 'is not.'];
    }

    /**
     * @param array<string, string> $entries
     */
    #[DataProvider('invalidMetadata')]
    public function testMetadataOutOfBoundsIsRefused(array $entries, string $error): void
    {
        $this->expectException(InvalidGuild::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Metadata($entries);
    }

    public function testAMetadataNameOfSixtyFourCharactersIsAccepted(): void
    {
        self::assertSame('x', new Metadata([str_repeat('a', 64) => 'x'])->get(str_repeat('a', 64)));
        self::assertSame(str_repeat('a', 500), new Metadata(['motto' => str_repeat('a', 500)])->get('motto'));
    }

    public function testTheLimitsHaveDefaultsAndBounds(): void
    {
        $defaults = new Limits();
        $lowest = new Limits(1, 1, 1);
        $highest = new Limits(100, 1000, 100);

        self::assertSame([10, 100, 10], [$defaults->guildsPerMember, $defaults->requestsPerGuild, $defaults->openGoals]);
        self::assertSame([1, 1, 1], [$lowest->guildsPerMember, $lowest->requestsPerGuild, $lowest->openGoals]);
        self::assertSame([100, 1000, 100], [$highest->guildsPerMember, $highest->requestsPerGuild, $highest->openGoals]);
    }

    /**
     * @return iterable<string, array{int, int, int, string}>
     */
    public static function invalidLimits(): iterable
    {
        yield 'no guild per member' => [0, 100, 10, 'The limit of guilds per member is 1 to 100; it is 0.'];
        yield 'too many guilds per member' => [101, 100, 10, 'The limit of guilds per member is 1 to 100; it is 101.'];
        yield 'no request per guild' => [10, 0, 10, 'The limit of waiting requests per guild is 1 to 1000; it is 0.'];
        yield 'too many requests per guild' => [10, 1001, 10, 'The limit of waiting requests per guild is 1 to 1000; it is 1001.'];
        yield 'no open goal' => [10, 100, 0, 'The limit of open goals per guild is 1 to 100; it is 0.'];
        yield 'too many open goals' => [10, 100, 101, 'The limit of open goals per guild is 1 to 100; it is 101.'];
    }

    #[DataProvider('invalidLimits')]
    public function testLimitsOutOfBoundsAreRefused(int $guilds, int $requests, int $goals, string $error): void
    {
        $this->expectException(InvalidGuild::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Limits($guilds, $requests, $goals);
    }

    public function testTheRolesSayWhatTheyAllow(): void
    {
        self::assertTrue(Role::Founder->administers());
        self::assertTrue(Role::Administrator->administers());
        self::assertFalse(Role::Member->administers());

        $allowed = [];

        foreach (Role::cases() as $actor) {
            foreach (Role::cases() as $target) {
                if ($actor->canRemove($target)) {
                    $allowed[] = $actor->value.'>'.$target->value;
                }
            }
        }

        self::assertSame(['founder>administrator', 'founder>member', 'administrator>member'], $allowed);
    }

    public function testAGoalKeepsItsValuesAndSaysWhenItIsOver(): void
    {
        $opened = new DateTimeImmutable('2026-12-01 00:00:00');
        $deadline = new DateTimeImmutable('2027-01-01 00:00:00');
        $goal = new Goal('a.B_9:x-'.str_repeat('k', 92), ResourceReference::of('guild', '1'), str_repeat('é', 100), 1_000_000_000, $opened, $deadline);

        self::assertFalse($goal->isOver(new DateTimeImmutable('2026-12-31 23:59:59')));
        self::assertTrue($goal->isOver($deadline));
        self::assertNull($goal->reachedAt);
        self::assertNull($goal->closedAt);

        $at = new DateTimeImmutable('2026-12-10 00:00:00');
        $reached = $goal->reached($at);
        $closed = $goal->closed($at);

        foreach ([$reached, $closed] as $copy) {
            self::assertSame([$goal->key, $goal->name, $goal->target, $opened, $deadline], [$copy->key, $copy->name, $copy->target, $copy->openedAt, $copy->deadline]);
            self::assertTrue($copy->guild->equals($goal->guild));
            self::assertTrue($copy->isOver($opened));
        }

        self::assertSame([$at, null], [$reached->reachedAt, $reached->closedAt]);
        self::assertSame([null, $at], [$closed->reachedAt, $closed->closedAt]);
        self::assertSame($at, $reached->closed($opened)->reachedAt);
        self::assertSame($at, $closed->reached($opened)->closedAt);

        $endless = new Goal('k', ResourceReference::of('guild', '1'), 'Goal', 1, $opened);
        self::assertFalse($endless->isOver(new DateTimeImmutable('2100-01-01')));
    }

    /**
     * @return iterable<string, array{string, string, int, ?string, string}>
     */
    public static function invalidGoals(): iterable
    {
        yield 'an empty key' => ['', 'Goal', 10, null, 'A goal key is 1 to 100 ASCII letters, digits, "_", ".", ":" or "-"; "" is not.'];
        yield 'a key too long' => [str_repeat('k', 101), 'Goal', 10, null, 'A goal key is 1 to 100'];
        yield 'a key with a space' => ['a b', 'Goal', 10, null, '"a b" is not.'];
        yield 'a key ending with a line break' => ["k\n", 'Goal', 10, null, 'is not.'];
        yield 'an empty name' => ['k', '', 10, null, 'A goal name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.'];
        yield 'a name too long' => ['k', str_repeat('a', 101), 10, null, 'A goal name is 1 to 100'];
        yield 'a name with a space around' => ['k', 'Goal ', 10, null, 'A goal name is 1 to 100'];
        yield 'a name with a control character' => ['k', "Go\nal", 10, null, 'A goal name is 1 to 100'];
        yield 'no target' => ['k', 'Goal', 0, null, 'A goal targets 1 to 1000000000 points; not 0.'];
        yield 'a target too high' => ['k', 'Goal', 1_000_000_001, null, 'A goal targets 1 to 1000000000 points; not 1000000001.'];
        yield 'a deadline at the opening' => ['k', 'Goal', 10, '2026-12-01 00:00:00', "A goal's deadline comes after its opening."];
        yield 'a deadline before the opening' => ['k', 'Goal', 10, '2026-11-30 00:00:00', "A goal's deadline comes after its opening."];
    }

    #[DataProvider('invalidGoals')]
    public function testAGoalOutOfBoundsIsRefused(string $key, string $name, int $target, ?string $deadline, string $error): void
    {
        $this->expectException(InvalidGuild::class);
        $this->expectExceptionMessageIsOrContains($error);

        new Goal($key, ResourceReference::of('guild', '1'), $name, $target, new DateTimeImmutable('2026-12-01 00:00:00'), null === $deadline ? null : new DateTimeImmutable($deadline));
    }

    public function testProgressSaysTheShareAndTheRemainder(): void
    {
        $goal = new Goal('k', ResourceReference::of('guild', '1'), 'Goal', 3, new DateTimeImmutable('2026-12-01'));

        $none = new GoalProgress($goal, 0, 0);
        $some = new GoalProgress($goal, 2, 1);
        $over = new GoalProgress($goal->reached(new DateTimeImmutable('2026-12-02')), 5, 2);

        self::assertSame([0, 3, false], [$none->percent(), $none->remaining(), $none->isReached()]);
        self::assertSame([66, 1, false, 1], [$some->percent(), $some->remaining(), $some->isReached(), $some->contributors]);
        self::assertSame([100, 0, true, 5], [$over->percent(), $over->remaining(), $over->isReached(), $over->points]);
    }

    public function testTheShareIsRoundedDownFromTheExactPoints(): void
    {
        $goal = new Goal('k', ResourceReference::of('guild', '1'), 'Goal', 100, new DateTimeImmutable('2026-12-01'));

        self::assertSame([1, 50, 100], [new GoalProgress($goal, 1, 1)->percent(), new GoalProgress($goal, 50, 1)->percent(), new GoalProgress($goal, 100, 1)->percent()]);
    }

    public function testAKeyIsCheckedFromOutsideTheGoal(): void
    {
        Goal::checkKey('a.B_9:x-', 'contribution');

        $this->expectException(InvalidGuild::class);
        $this->expectExceptionMessageIsOrContains('A contribution key is 1 to 100 ASCII letters, digits, "_", ".", ":" or "-"; "a b" is not.');

        Goal::checkKey('a b', 'contribution');
    }

    public function testEachRefusalSaysWhy(): void
    {
        $messages = [];

        foreach (Refusal::cases() as $refusal) {
            $refused = GuildRefused::because($refusal);
            self::assertSame($refusal, $refused->refusal);
            $messages[$refusal->value] = $refused->getMessage();
        }

        self::assertSame([
            'already_founded' => 'A guild already holds this reference.',
            'already_member' => 'This member already belongs to the guild.',
            'already_requested' => 'This member already asked to join the guild.',
            'not_member' => 'This member does not belong to the guild.',
            'no_request' => 'This member has no request to join the guild.',
            'full' => 'The guild holds as many members as its capacity.',
            'too_many_guilds' => 'This member belongs to, or asked to join, as many guilds as allowed.',
            'too_many_requests' => 'The guild holds as many waiting requests as allowed.',
            'not_allowed' => 'The role of this member does not allow this act.',
            'founder_stays' => 'The founder hands the guild to another member before leaving it.',
            'below_members' => 'The capacity cannot go below the members the guild holds.',
            'goal_exists' => 'A goal already holds this key.',
            'too_many_goals' => 'The guild holds as many open goals as allowed.',
            'goal_over' => 'The goal is reached, closed or past its deadline.',
        ], $messages);
    }

    public function testTheUnknownSayWhatIsMissing(): void
    {
        self::assertSame('No guild is known as "guild:9".', UnknownGuild::named('guild:9')->getMessage());
        self::assertSame('No goal is known as "g".', UnknownGuild::goal('g')->getMessage());
    }

    private function guild(string $name, int $capacity = 50): Guild
    {
        return new Guild(ResourceReference::of('guild', '1'), $name, Admission::Open, $capacity, new Metadata(), new DateTimeImmutable('2026-11-21 10:00:00'));
    }
}
