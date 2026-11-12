<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\WritingGoals\Contract\GoalStore;
use Tilttek\WritingGoals\Contract\LogStore;
use Tilttek\WritingGoals\Event\GoalReached;
use Tilttek\WritingGoals\Exception\InvalidGoal;
use Tilttek\WritingGoals\Exception\UnknownGoal;

/**
 * Keeps an author's goals, logs what they write and answers the plan of a goal on the author's today. No streak is
 * counted: a day without writing only reshapes the quotas of the days after. The service keeps no state between two
 * calls.
 */
final readonly class WritingGoals
{
    public function __construct(
        private GoalStore $goals,
        private LogStore $logs,
        private ClockInterface $clock,
        private EventDispatcherInterface $dispatcher,
        private Planner $planner = new Planner(),
    ) {
    }

    /**
     * Keeps a new goal, or the goal changed — a later deadline, another curve —: what was logged stays.
     */
    public function save(Goal $goal): void
    {
        $this->goals->save($goal);
    }

    /**
     * @throws UnknownGoal
     */
    public function goal(string $id): Goal
    {
        return $this->goals->find($id) ?? throw UnknownGoal::withId($id);
    }

    /**
     * @return list<Goal>
     */
    public function goalsOf(ResourceReference $author): array
    {
        return $this->goals->goalsOf($author);
    }

    /**
     * The author's today, in the goal's timezone.
     */
    public function today(Goal $goal): Day
    {
        return Day::of($this->clock->now(), $goal->timezone);
    }

    /**
     * Adds to what was written on a day, today when none is given; a negative count takes back a count logged too high.
     * Reaching the target dispatches `GoalReached`.
     *
     * @throws UnknownGoal
     */
    public function log(string $id, int $count, ?Day $day = null): Plan
    {
        if (abs($count) > Progress::MAX_PER_DAY) {
            throw InvalidGoal::because(\sprintf('A count logged is -%1$d to %1$d; %2$d is not one.', Progress::MAX_PER_DAY, $count));
        }

        return $this->change($id, $day, fn (Goal $goal, Day $on): int => $this->logs->add($goal->id, $on, $count));
    }

    /**
     * Replaces what was written on a day, today when none is given.
     *
     * @throws UnknownGoal
     */
    public function correct(string $id, int $count, ?Day $day = null): Plan
    {
        return $this->change($id, $day, fn (Goal $goal, Day $on) => $this->logs->set($goal->id, $on, $count));
    }

    /**
     * The goal as it stands on the author's today.
     *
     * @throws UnknownGoal
     */
    public function plan(string $id): Plan
    {
        $goal = $this->goal($id);

        return $this->planner->plan($goal, $this->logs->progress($goal->id), $this->today($goal));
    }

    /**
     * Removes every goal of the author and what was logged toward them.
     *
     * @return int how many goals were removed
     */
    public function erase(ResourceReference $author): int
    {
        $goals = $this->goals->goalsOf($author);

        foreach ($goals as $goal) {
            $this->logs->forget($goal->id);
            $this->goals->remove($goal->id);
        }

        return \count($goals);
    }

    /**
     * @param callable(Goal, Day): mixed $write
     */
    private function change(string $id, ?Day $day, callable $write): Plan
    {
        $goal = $this->goal($id);
        $today = $this->today($goal);
        $on = $day ?? $today;

        if ($on->isAfter($today)) {
            throw InvalidGoal::because(\sprintf('Words are logged on a day that has come; %s is after %s.', $on->toString(), $today->toString()));
        }

        if ($on->isBefore($goal->start)) {
            throw InvalidGoal::because(\sprintf("Words are logged from the goal's first day; %s is before %s.", $on->toString(), $goal->start->toString()));
        }

        $before = $this->logs->progress($goal->id)->total();
        $write($goal, $on);
        $progress = $this->logs->progress($goal->id);

        if ($before < $goal->target && $progress->total() >= $goal->target) {
            $this->dispatcher->dispatch(new GoalReached($goal->id, $goal->author, $on));
        }

        return $this->planner->plan($goal, $progress, $today);
    }
}
