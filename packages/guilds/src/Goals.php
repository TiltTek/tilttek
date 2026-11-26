<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Guilds\Contract\GoalStore;
use Tilttek\Guilds\Contract\GuildObserver;
use Tilttek\Guilds\Exception\GuildRefused;
use Tilttek\Guilds\Exception\InvalidGuild;
use Tilttek\Guilds\Exception\UnknownGuild;

/**
 * The group track of the guilds: a goal its founder or an administrator opens, which every member's contributions
 * advance together until its target is met.
 *
 * ```php
 * $goals->open('lanterns-december', $guild, 'Play 200 games in December', 200, $founder, $deadline);
 * $goals->contribute('lanterns-december', $member, 3, 'game:9812');   // a key makes a repeated call count once
 * $goals->progress('lanterns-december')->percent();
 * ```
 *
 * Only members contribute; a member who leaves keeps the points they brought in the guild's total. The track ranks
 * nobody inside the guild: it tells the total, how many brought points, and each member their own share.
 */
final readonly class Goals
{
    /**
     * @var list<GuildObserver>
     */
    private array $observers;

    /**
     * @param iterable<GuildObserver> $observers
     */
    public function __construct(
        private GoalStore $store,
        private Guilds $guilds,
        private ClockInterface $clock,
        private Limits $limits = new Limits(),
        iterable $observers = [],
    ) {
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * The founder or an administrator opens a goal of the guild, under a key the application gives.
     */
    public function open(string $key, ResourceReference $guild, string $name, int $target, ResourceReference $by, ?DateTimeImmutable $deadline = null): Goal
    {
        $now = $this->clock->now();
        $goal = new Goal($key, $guild, $name, $target, $now, $deadline);
        $this->guilds->guild($guild);
        $membership = $this->guilds->membership($guild, $by);

        if (!$membership instanceof Membership || !$membership->role->administers()) {
            throw GuildRefused::because(Refusal::NotAllowed);
        }

        if ($this->store->goal($key) instanceof Goal) {
            throw GuildRefused::because(Refusal::GoalExists);
        }

        $open = array_filter($this->store->goalsOf($guild->toString()), static fn (Goal $goal): bool => !$goal->isOver($now));

        if (\count($open) >= $this->limits->openGoals) {
            throw GuildRefused::because(Refusal::TooManyGoals);
        }

        $this->store->saveGoal($goal);

        return $goal;
    }

    /**
     * @throws UnknownGuild
     */
    public function goal(string $key): Goal
    {
        return $this->store->goal($key) ?? throw UnknownGuild::goal($key);
    }

    /**
     * The guild's goals, the earliest opened first.
     *
     * @return list<Goal>
     */
    public function goalsOf(ResourceReference $guild): array
    {
        return $this->store->goalsOf($guild->toString());
    }

    /**
     * A member brings points to a goal of their guild. Under a key, a contribution made again is counted once and the
     * goal's progress returned; the contribution that meets the target reaches the goal, which then takes no more.
     */
    public function contribute(string $goal, ResourceReference $member, int $points, ?string $key = null): GoalProgress
    {
        if ($points < 1 || $points > Goal::MAX_TARGET) {
            throw InvalidGuild::because(\sprintf('A contribution brings 1 to %d points; not %d.', Goal::MAX_TARGET, $points));
        }

        if (null !== $key) {
            Goal::checkKey($key, 'contribution');
        }

        $found = $this->goal($goal);

        if (null !== $key && $this->store->contribution($goal, $key) instanceof Contribution) {
            return $this->progress($goal);
        }

        if (!$this->guilds->isMember($member, $found->guild)) {
            throw GuildRefused::because(Refusal::NotMember);
        }

        $now = $this->clock->now();

        if ($found->isOver($now)) {
            throw GuildRefused::because(Refusal::GoalOver);
        }

        $this->store->addContribution(new Contribution($goal, $member, $points, $key, $now));
        $total = $this->store->points($goal);

        if ($total >= $found->target) {
            $this->store->saveGoal($found->reached($now));
            $event = new GoalReached($goal, $found->guild->toString(), $total, $now);

            foreach ($this->observers as $observer) {
                $observer->goalReached($event);
            }
        }

        return $this->progress($goal);
    }

    public function progress(string $goal): GoalProgress
    {
        return new GoalProgress($this->goal($goal), $this->store->points($goal), $this->store->contributors($goal));
    }

    /**
     * The points the member brought to the goal: their own share, for them to read.
     */
    public function contributed(string $goal, ResourceReference $member): int
    {
        $this->goal($goal);

        return $this->store->pointsOf($goal, $member->toString());
    }

    /**
     * The founder or an administrator closes a goal before its target or its deadline.
     */
    public function close(string $goal, ResourceReference $by): Goal
    {
        $found = $this->goal($goal);
        $membership = $this->guilds->membership($found->guild, $by);

        if (!$membership instanceof Membership || !$membership->role->administers()) {
            throw GuildRefused::because(Refusal::NotAllowed);
        }

        $now = $this->clock->now();

        if ($found->isOver($now)) {
            throw GuildRefused::because(Refusal::GoalOver);
        }

        $closed = $found->closed($now);
        $this->store->saveGoal($closed);

        return $closed;
    }

    /**
     * Forgets the member on every contribution they made; the guilds keep the points.
     */
    public function erase(ResourceReference $member): void
    {
        $this->store->forget($member->toString());
    }
}
