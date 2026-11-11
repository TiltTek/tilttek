<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

/**
 * Counts the runs of a member's active days or weeks. A run goes on through each active unit, which adds one, through
 * each frozen unit and each unit the grace forgives, which add nothing, and breaks on any other missed unit; the
 * current unit and those after it are not over, so missing them breaks nothing yet.
 *
 * The units where nothing can change — before a run starts again, and from the current unit on — are skipped to the
 * next active unit, so the work grows with the active, frozen and forgiven units, not with the span between the first
 * active unit and now: a gesture dated centuries away costs no more than another.
 */
final class StreakCalculator
{
    private function __construct()
    {
    }

    /**
     * @param list<int> $active the active units, sorted, each once
     * @param list<int> $frozen the frozen units
     */
    public static function status(string $streak, array $active, array $frozen, ?Grace $grace, int $now): StreakStatus
    {
        if ([] === $active) {
            return new StreakStatus($streak, graceLeft: $grace->allowed ?? 0);
        }

        $isFrozen = array_fill_keys($frozen, true);
        $run = 0;
        $best = $run;
        $start = null;
        $forgiven = [];
        $count = \count($active);
        $last = $active[$count - 1];
        $end = max($last, $now);
        // The index of the first active unit not yet counted.
        $next = 0;
        $unit = $active[0];

        while ($unit <= $end) {
            if ($next < $count && $active[$next] === $unit) {
                ++$next;
                $start ??= $unit;
                $best = max($best, ++$run);
            } elseif ($unit >= $now || null === $start) {
                // Nothing changes before the next active unit: the units between are not visited.
                $unit = $next < $count ? $active[$next] : $end + 1;

                continue;
            } elseif (!isset($isFrozen[$unit])) {
                // A frozen unit keeps the run as it is; another missed unit is forgiven or breaks it.
                if ($grace instanceof Grace && self::forgiven($forgiven, $unit, $grace) < $grace->allowed) {
                    $forgiven[] = $unit;
                } else {
                    $run = 0;
                    $start = null;
                    $forgiven = [];
                }
            }

            ++$unit;
        }

        return new StreakStatus($streak, $run, $best, $start, $last, $grace instanceof Grace ? $grace->allowed - self::forgiven($forgiven, $now, $grace) : 0);
    }

    /**
     * How many of the units forgiven fall in the grace's window ending at the unit.
     *
     * @param list<int> $forgiven
     */
    private static function forgiven(array $forgiven, int $unit, Grace $grace): int
    {
        return \count(array_filter($forgiven, static fn (int $missed): bool => $missed > $unit - $grace->window));
    }
}
