<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\WritingGoals\Curve;
use Tilttek\WritingGoals\DailyQuota;
use Tilttek\WritingGoals\Day;
use Tilttek\WritingGoals\Goal;
use Tilttek\WritingGoals\Plan;
use Tilttek\WritingGoals\Planner;
use Tilttek\WritingGoals\Progress;
use Tilttek\WritingGoals\Unit;
use Tilttek\WritingGoals\WritingDays;

#[CoversClass(Planner::class)]
#[CoversClass(Plan::class)]
#[CoversClass(DailyQuota::class)]
final class PlannerTest extends TestCase
{
    public function testALinearPlanOnItsFirstDay(): void
    {
        $plan = $this->plan($this->goal(1000, 10), Progress::none(), '2026-11-01');

        self::assertSame(array_fill(0, 10, 100), $this->quotas($plan));
        self::assertSame('2026-11-01', $plan->quotas[0]->day->toString());
        self::assertSame('2026-11-10', $plan->quotas[9]->day->toString());
        self::assertSame([100, 100, 0, 0, 1000, 0], [$plan->todayQuota, $plan->leftToday(), $plan->written, $plan->writtenToday, $plan->remaining(), $plan->percent()]);
        self::assertFalse($plan->isReached());
        self::assertFalse($plan->isOverdue());
        self::assertSame('2026-11-01', $plan->today->toString());
    }

    public function testMissedDaysOnlyReshapeTheDaysAfter(): void
    {
        $plan = $this->plan($this->goal(1000, 10), Progress::none(), '2026-11-03');

        self::assertSame(array_fill(0, 8, 125), $this->quotas($plan));
        self::assertSame(125, $plan->todayQuota);
    }

    public function testWordsWrittenAheadLowerTheDaysAfter(): void
    {
        $plan = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 280]), '2026-11-02');

        self::assertSame(array_fill(0, 9, 80), $this->quotas($plan));
        self::assertSame([280, 0, 720, 28], [$plan->written, $plan->writtenToday, $plan->remaining(), $plan->percent()]);
    }

    public function testWhatIsWrittenTodayCountsTowardTodaysQuota(): void
    {
        $plan = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 60]), '2026-11-01');

        self::assertSame([100, 60, 40, 60, 940], [$plan->todayQuota, $plan->writtenToday, $plan->leftToday(), $plan->written, $plan->remaining()]);

        $beyond = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 160]), '2026-11-01');
        self::assertSame([100, 0], [$beyond->todayQuota, $beyond->leftToday()]);
    }

    public function testTheQuotasAddUpToWhatRemains(): void
    {
        $plan = $this->plan($this->goal(100, 3), Progress::none(), '2026-11-01');

        self::assertSame([33, 33, 34], $this->quotas($plan));
        self::assertSame(1_000_000_000, array_sum($this->quotas($this->plan($this->goal(1_000_000_000, 3660, Curve::frontLoaded()), Progress::none(), '2026-11-01'))));
    }

    public function testFrontAndBackLoadedPlans(): void
    {
        self::assertSame([70, 60, 50, 40], $this->quotas($this->plan($this->goal(220, 4, Curve::frontLoaded()), Progress::none(), '2026-11-01')));
        self::assertSame([40, 50, 60, 70], $this->quotas($this->plan($this->goal(220, 4, Curve::backLoaded()), Progress::none(), '2026-11-01')));
        self::assertSame([122, 98], $this->quotas($this->plan($this->goal(220, 4, Curve::frontLoaded()), Progress::none(), '2026-11-03')));
    }

    public function testACustomPlan(): void
    {
        self::assertSame([100, 100, 0, 0, 100, 100], $this->quotas($this->plan($this->goal(400, 6, Curve::custom([1, 0, 1])), Progress::none(), '2026-11-01')));
    }

    public function testDaysOfWeightZeroShareWhatRemainsWhenNoOtherDayIsLeft(): void
    {
        $plan = $this->plan($this->goal(100, 4, Curve::custom([1, 0])), Progress::none(), '2026-11-03');

        self::assertSame([50, 50], $this->quotas($plan));
    }

    public function testADayWithoutWritingHasNoQuota(): void
    {
        $goal = new Goal('weekdays', ResourceReference::of('member', '1'), 500, Day::fromString('2026-11-02'), Day::fromString('2026-11-15'), Unit::Words, null, WritingDays::on(1, 2, 3, 4, 5));
        $plan = $this->plan($goal, Progress::fromArray(['2026-11-02' => 50]), '2026-11-07');

        self::assertSame(0, $plan->todayQuota);
        self::assertSame(['2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13'], array_map(static fn (DailyQuota $quota): string => $quota->day->toString(), $plan->quotas));
        self::assertSame(array_fill(0, 5, 90), $this->quotas($plan));
    }

    public function testAPlanBeforeItsStart(): void
    {
        $plan = $this->plan($this->goal(1000, 10), Progress::none(), '2026-10-30');

        self::assertSame(0, $plan->todayQuota);
        self::assertSame(array_fill(0, 10, 100), $this->quotas($plan));
    }

    public function testThePercentIsRoundedDown(): void
    {
        $plan = $this->plan($this->goal(101, 10), Progress::fromArray(['2026-11-01' => 100]), '2026-11-01');

        self::assertSame(99, $plan->percent());
    }

    public function testAReachedGoal(): void
    {
        $plan = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 600, '2026-11-02' => 500]), '2026-11-02');

        self::assertTrue($plan->isReached());
        self::assertFalse($plan->isOverdue());
        self::assertSame([0, 100, 1100, 0], [$plan->remaining(), $plan->percent(), $plan->written, $plan->leftToday()]);
        self::assertSame(44, $plan->todayQuota);

        $beyond = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 1200]), '2026-11-02');
        self::assertSame([0, 0, 1200], [$beyond->todayQuota, $beyond->remaining(), $beyond->written]);
        self::assertSame(array_fill(0, 9, 0), $this->quotas($beyond));

        $exactly = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 1000]), '2026-11-02');
        self::assertTrue($exactly->isReached());
        self::assertSame([0, 0], [$exactly->todayQuota, $exactly->remaining()]);
        self::assertSame(array_fill(0, 9, 0), $this->quotas($exactly));
    }

    public function testAGoalPastItsDeadline(): void
    {
        $plan = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 600]), '2026-11-11');

        self::assertTrue($plan->isOverdue());
        self::assertSame([], $plan->quotas);
        self::assertSame([0, 400, 60], [$plan->todayQuota, $plan->remaining(), $plan->percent()]);

        $onTheDeadline = $this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 600]), '2026-11-10');
        self::assertFalse($onTheDeadline->isOverdue());
        self::assertSame([400], $this->quotas($onTheDeadline));

        self::assertFalse($this->plan($this->goal(1000, 10), Progress::fromArray(['2026-11-01' => 1000]), '2026-11-12')->isOverdue());
    }

    private function goal(int $target, int $days, ?Curve $curve = null): Goal
    {
        $start = Day::fromString('2026-11-01');

        return new Goal('goal', ResourceReference::of('member', '1'), $target, $start, $start->plus($days - 1), Unit::Words, $curve);
    }

    private function plan(Goal $goal, Progress $progress, string $today): Plan
    {
        return new Planner()->plan($goal, $progress, Day::fromString($today));
    }

    /**
     * @return list<int>
     */
    private function quotas(Plan $plan): array
    {
        return array_map(static fn (DailyQuota $quota): int => $quota->quota, $plan->quotas);
    }
}
