<?php

declare(strict_types=1);

namespace Tilttek\Guilds\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Guilds\Contribution;
use Tilttek\Guilds\Exception\GuildRefused;
use Tilttek\Guilds\Exception\InvalidGuild;
use Tilttek\Guilds\Exception\UnknownGuild;
use Tilttek\Guilds\Goal;
use Tilttek\Guilds\GoalProgress;
use Tilttek\Guilds\GoalReached;
use Tilttek\Guilds\Goals;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\Limits;
use Tilttek\Guilds\Refusal;
use Tilttek\Guilds\Store\InMemoryGoalStore;
use Tilttek\Guilds\Store\InMemoryGuildStore;
use Tilttek\Guilds\Tests\Unit\Fixture\Recorder;

#[CoversClass(Goals::class)]
#[CoversClass(InMemoryGoalStore::class)]
#[CoversClass(GoalReached::class)]
final class GoalsTest extends TestCase
{
    private MockClock $clock;

    private InMemoryGoalStore $store;

    private Recorder $recorder;

    private Guilds $guilds;

    private Goals $goals;

    private ResourceReference $guild;

    private ResourceReference $founder;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-12-01 10:00:00', 'UTC');
        $this->store = new InMemoryGoalStore();
        $this->recorder = new Recorder();
        $this->guilds = new Guilds(new InMemoryGuildStore(), $this->clock);
        $this->goals = new Goals($this->store, $this->guilds, $this->clock, new Limits(openGoals: 2), [$this->recorder]);
        $this->guild = ResourceReference::of('guild', '44');
        $this->founder = $this->member('1');
        $this->guilds->found($this->guild, 'Lanterns', $this->founder);
        $this->guilds->join($this->guild, $this->member('2'));
        $this->guilds->join($this->guild, $this->member('3'));
    }

    public function testAnAdministratorOpensAGoal(): void
    {
        $this->guilds->promote($this->guild, $this->member('2'), $this->founder);
        $deadline = new DateTimeImmutable('2027-01-01 00:00:00');

        $goal = $this->goals->open('december', $this->guild, 'Play 200 games', 200, $this->member('2'), $deadline);

        self::assertSame(['december', 'Play 200 games', 200, $deadline], [$goal->key, $goal->name, $goal->target, $goal->deadline]);
        self::assertSame('2026-12-01 10:00:00', $goal->openedAt->format('Y-m-d H:i:s'));
        self::assertSame($goal, $this->goals->goal('december'));
        self::assertSame([$goal], $this->goals->goalsOf($this->guild));
        $this->assertRefused(Refusal::GoalExists, fn (): Goal => $this->goals->open('december', $this->guild, 'Again', 10, $this->founder));
        $this->assertRefused(Refusal::NotAllowed, fn (): Goal => $this->goals->open('mine', $this->guild, 'Mine', 10, $this->member('3')));
        $this->assertRefused(Refusal::NotAllowed, fn (): Goal => $this->goals->open('stranger', $this->guild, 'Stranger', 10, $this->member('9')));
    }

    public function testAGoalOfAnUnknownGuildIsRefused(): void
    {
        $this->expectException(UnknownGuild::class);
        $this->expectExceptionMessageIsOrContains('No guild is known as "guild:45".');

        $this->goals->open('k', ResourceReference::of('guild', '45'), 'Goal', 10, $this->founder);
    }

    public function testTheOpenGoalsOfAGuildAreBounded(): void
    {
        $this->goals->open('a', $this->guild, 'A', 10, $this->founder);
        $this->goals->open('b', $this->guild, 'B', 10, $this->founder, new DateTimeImmutable('2026-12-01 11:00:00'));

        $this->assertRefused(Refusal::TooManyGoals, fn (): Goal => $this->goals->open('c', $this->guild, 'C', 10, $this->founder));

        $this->clock->modify('+1 hour');
        $this->goals->open('c', $this->guild, 'C', 10, $this->founder);
        $this->goals->close('a', $this->founder);
        $this->goals->open('d', $this->guild, 'D', 10, $this->founder);

        self::assertSame(['a', 'b', 'c', 'd'], array_map(static fn (Goal $goal): string => $goal->key, $this->goals->goalsOf($this->guild)));
    }

    public function testGoalsOpenedTogetherAreReadInTheOrderOfTheirKeys(): void
    {
        $this->goals->open('b', $this->guild, 'B', 10, $this->founder);
        $this->goals->open('a', $this->guild, 'A', 10, $this->founder);

        $this->guilds->found(ResourceReference::of('guild', '45'), 'Torches', $this->founder);
        $this->goals->open('z', ResourceReference::of('guild', '45'), 'Z', 10, $this->founder);

        self::assertSame(['a', 'b'], array_map(static fn (Goal $goal): string => $goal->key, $this->goals->goalsOf($this->guild)));
        self::assertSame(['z'], array_map(static fn (Goal $goal): string => $goal->key, $this->goals->goalsOf(ResourceReference::of('guild', '45'))));
    }

    public function testMembersContributionsAdvanceTheGoalUntilItIsReached(): void
    {
        $this->goals->open('december', $this->guild, 'Play 10 games', 10, $this->founder);

        $first = $this->goals->contribute('december', $this->member('2'), 4);
        $second = $this->goals->contribute('december', $this->member('3'), 3);
        $third = $this->goals->contribute('december', $this->member('2'), 2);

        self::assertSame([4, 1, 40], [$first->points, $first->contributors, $first->percent()]);
        self::assertSame([7, 2], [$second->points, $second->contributors]);
        self::assertSame([9, 2, 1, false], [$third->points, $third->contributors, $third->remaining(), $third->isReached()]);
        self::assertSame(6, $this->goals->contributed('december', $this->member('2')));
        self::assertSame(0, $this->goals->contributed('december', $this->founder));
        self::assertSame([], $this->recorder->reachedSummary());

        $this->clock->modify('+1 hour');
        $reached = $this->goals->contribute('december', $this->founder, 5);

        self::assertTrue($reached->isReached());
        self::assertSame([14, 3, 100], [$reached->points, $reached->contributors, $reached->percent()]);
        self::assertSame('2026-12-01 11:00:00', $this->goals->goal('december')->reachedAt?->format('Y-m-d H:i:s'));
        self::assertSame(['december guild:44 14 2026-12-01 11:00:00'], $this->recorder->reachedSummary());
        $this->assertRefused(Refusal::GoalOver, fn (): GoalProgress => $this->goals->contribute('december', $this->member('2'), 1));
    }

    public function testTheContributionMeetingTheTargetExactlyReachesTheGoal(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 3, $this->founder);

        self::assertTrue($this->goals->contribute('k', $this->member('2'), 3)->isReached());
    }

    public function testAContributionUnderAKeyCountsOnce(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 10, $this->founder);

        $this->goals->contribute('k', $this->member('2'), 2, 'game:9812');

        $again = $this->goals->contribute('k', $this->member('2'), 2, 'game:9812');
        $this->goals->contribute('k', $this->member('2'), 2, 'game:9813');

        self::assertSame(2, $again->points);
        self::assertSame(4, $this->goals->progress('k')->points);
        self::assertSame('game:9812', $this->store->contribution('k', 'game:9812')?->key);
        self::assertNull($this->store->contribution('k', 'game:1'));
        self::assertNull($this->store->contribution('other', 'game:9812'));
    }

    public function testAKeyAlreadyCountedAnswersEvenOnceTheGoalIsOver(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 2, $this->founder);
        $this->goals->contribute('k', $this->member('2'), 2, 'game:1');

        self::assertSame(2, $this->goals->contribute('k', $this->member('2'), 2, 'game:1')->points);
    }

    public function testOnlyMembersContribute(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 10, $this->founder);
        $this->guilds->leave($this->guild, $this->member('3'));

        $this->assertRefused(Refusal::NotMember, fn (): GoalProgress => $this->goals->contribute('k', $this->member('9'), 1));
        $this->assertRefused(Refusal::NotMember, fn (): GoalProgress => $this->goals->contribute('k', $this->member('3'), 1));
    }

    public function testAMemberWhoLeavesKeepsTheirPointsInTheGuild(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 10, $this->founder);
        $this->goals->contribute('k', $this->member('3'), 4);

        $this->guilds->leave($this->guild, $this->member('3'));

        self::assertSame(4, $this->goals->progress('k')->points);
    }

    public function testAGoalTakesNoContributionAtItsDeadline(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 10, $this->founder, new DateTimeImmutable('2026-12-01 12:00:00'));
        $this->clock->modify('+1 hour 59 minutes 59 seconds');
        $this->goals->contribute('k', $this->member('2'), 1);

        $this->clock->modify('+1 second');

        $this->assertRefused(Refusal::GoalOver, fn (): GoalProgress => $this->goals->contribute('k', $this->member('2'), 1));
        $this->assertRefused(Refusal::GoalOver, fn (): Goal => $this->goals->close('k', $this->founder));
    }

    public function testAnAdministratorClosesAGoal(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 10, $this->founder);
        $this->clock->modify('+1 day');

        $this->assertRefused(Refusal::NotAllowed, fn (): Goal => $this->goals->close('k', $this->member('2')));
        $this->assertRefused(Refusal::NotAllowed, fn (): Goal => $this->goals->close('k', $this->member('9')));
        $closed = $this->goals->close('k', $this->founder);

        self::assertSame('2026-12-02 10:00:00', $closed->closedAt?->format('Y-m-d H:i:s'));
        self::assertSame($closed, $this->goals->goal('k'));
        $this->assertRefused(Refusal::GoalOver, fn (): GoalProgress => $this->goals->contribute('k', $this->member('2'), 1));
        $this->assertRefused(Refusal::GoalOver, fn (): Goal => $this->goals->close('k', $this->founder));
    }

    public function testContributionsOutOfBoundsAreRefused(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 10, $this->founder);

        foreach ([0, 1_000_000_001] as $points) {
            try {
                $this->goals->contribute('k', $this->member('2'), $points);
                self::fail('The points were accepted.');
            } catch (InvalidGuild $invalid) {
                self::assertSame(\sprintf('A contribution brings 1 to 1000000000 points; not %d.', $points), $invalid->getMessage());
            }
        }

        $this->expectException(InvalidGuild::class);
        $this->expectExceptionMessageIsOrContains('A contribution key is 1 to 100 ASCII letters, digits, "_", ".", ":" or "-"; "a b" is not.');

        $this->goals->contribute('k', $this->member('2'), 1, 'a b');
    }

    public function testTheLargestContributionIsAccepted(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 1_000_000_000, $this->founder);

        self::assertTrue($this->goals->contribute('k', $this->member('2'), 1_000_000_000)->isReached());
    }

    public function testAnUnknownGoalIsReported(): void
    {
        foreach ([fn (): GoalProgress => $this->goals->progress('none'), fn (): int => $this->goals->contributed('none', $this->founder), fn (): GoalProgress => $this->goals->contribute('none', $this->founder, 1), fn (): Goal => $this->goals->close('none', $this->founder)] as $act) {
            try {
                $act();
                self::fail('The goal was found.');
            } catch (UnknownGuild $unknown) {
                self::assertSame('No goal is known as "none".', $unknown->getMessage());
            }
        }
    }

    public function testErasingAMemberKeepsTheirPointsWithTheGuild(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 100, $this->founder);
        $this->goals->contribute('k', $this->member('2'), 4, 'a');
        $this->goals->contribute('k', $this->member('3'), 3);
        $this->goals->contribute('k', $this->member('2'), 1);

        $this->goals->erase($this->member('2'));
        $this->goals->erase($this->member('3'));

        $progress = $this->goals->progress('k');
        self::assertSame([8, 1], [$progress->points, $progress->contributors]);
        self::assertSame(0, $this->goals->contributed('k', $this->member('2')));
        $contribution = $this->store->contribution('k', 'a');
        self::assertInstanceOf(Contribution::class, $contribution);
        self::assertNull($contribution->member);
        self::assertSame([4, 'k'], [$contribution->points, $contribution->goal]);
    }

    public function testErasingOneMemberLeavesTheOthersContributionsNamed(): void
    {
        $this->goals->open('k', $this->guild, 'Goal', 100, $this->founder);
        $this->goals->contribute('k', $this->member('2'), 4);
        $this->goals->contribute('k', $this->member('3'), 3);
        $this->goals->contribute('k', $this->member('3'), 2);

        $this->goals->erase($this->member('2'));

        self::assertSame([0, 5], [$this->goals->contributed('k', $this->member('2')), $this->goals->contributed('k', $this->member('3'))]);
        self::assertSame([9, 2], [$this->goals->progress('k')->points, $this->goals->progress('k')->contributors]);

        $this->goals->erase($this->member('3'));

        self::assertSame([9, 1], [$this->goals->progress('k')->points, $this->goals->progress('k')->contributors]);
    }

    private function assertRefused(Refusal $refusal, callable $act): void
    {
        try {
            $act();
            self::fail(\sprintf('The act was not refused for "%s".', $refusal->value));
        } catch (GuildRefused $guildRefused) {
            self::assertSame($refusal, $guildRefused->refusal);
        }
    }

    private function member(string $id): ResourceReference
    {
        return ResourceReference::of('member', $id);
    }
}
