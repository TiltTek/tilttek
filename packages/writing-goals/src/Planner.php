<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

/**
 * Shares what remains of a goal among its writing days left, in proportion to the weights its curve gives them by their
 * place in the whole plan. A day without writing is not a failure: its share moves to the days after, which keep the
 * curve's shape. The quotas are whole numbers whose sum is exactly what remains.
 */
final readonly class Planner
{
    public function plan(Goal $goal, Progress $progress, Day $today): Plan
    {
        $writtenBefore = $progress->before($today);
        $writtenToday = $progress->on($today);
        $remaining = max(0, $goal->target - $writtenBefore);

        $days = $goal->writingDaysOfPlan();
        $count = \count($days);
        $left = [];
        $weights = [];

        foreach ($days as $position => $day) {
            if (!$day->isBefore($today)) {
                $left[] = $day;
                $weights[] = $goal->curve->weight($position, $count);
            }
        }

        $total = array_sum($weights);

        if (0 === $total) {
            $weights = array_fill(0, \count($left), 1);
            $total = \count($left);
        }

        $quotas = [];
        $todayQuota = 0;
        $cumulative = 0;
        $given = 0;

        foreach ($left as $index => $day) {
            $cumulative += $weights[$index];
            $share = intdiv($remaining * $cumulative, $total);
            $quota = new DailyQuota($day, $share - $given);
            $given = $share;
            $quotas[] = $quota;

            if ($day->equals($today)) {
                $todayQuota = $quota->quota;
            }
        }

        return new Plan($goal, $today, $writtenBefore + $writtenToday, $writtenToday, $todayQuota, $quotas);
    }
}
