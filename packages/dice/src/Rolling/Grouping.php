<?php

declare(strict_types=1);

namespace Tilttek\Dice\Rolling;

use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Validator;

/**
 * The groups a set of dice forms, by their values: the sets of the same value, and the groups that reach a sum — the
 * most of them up to `OPTIMAL` dice, found greedily beyond.
 *
 * @internal
 */
final class Grouping
{
    /**
     * The sets of dice of the same value, wide enough and of a height that matches, the widest first and, of equal
     * width, the highest first.
     *
     * @param list<int> $values
     *
     * @return list<list<int>>
     */
    public static function occurrences(array $values, Occurrences $occurrences): array
    {
        $sets = [];

        foreach ($values as $value) {
            $sets[$value][] = $value;
        }

        $sets = array_filter($sets, static fn (array $set, int $value): bool => \count($set) >= $occurrences->width && (!$occurrences->height instanceof Validator || $occurrences->height->matches($value)), \ARRAY_FILTER_USE_BOTH);
        usort($sets, static fn (array $a, array $b): int => [\count($b), $b[0]] <=> [\count($a), $a[0]]);

        return $sets;
    }

    /**
     * The most dice searched for the most groups; beyond, the groups are found greedily.
     */
    public const int OPTIMAL = 12;

    /**
     * The groups of dice that each reach the sum, and whether they are the most the dice can form. Up to `OPTIMAL`
     * dice, the search finds the most groups; beyond, one greedy pass does. Each group is listed highest die first,
     * the groups by their highest die, and dice that belong to no group are left out.
     *
     * @param list<int> $values
     *
     * @return array{list<list<int>>, bool}
     */
    public static function reaching(array $values, int $sum): array
    {
        rsort($values);

        if (\count($values) > self::OPTIMAL) {
            return [self::greedy($values, $sum), false];
        }

        $sums = [0];

        foreach ($values as $index => $value) {
            $bit = 1 << $index;

            for ($mask = 0; $mask < $bit; ++$mask) {
                $sums[$bit | $mask] = $sums[$mask] + $value;
            }
        }

        $minimal = [];

        // The groups from which no die can leave with the group still reaching the sum, by their highest die, the
        // larger masks first.
        foreach (array_reverse(array_keys($sums)) as $mask) {
            if ($sums[$mask] >= $sum && self::minimal($mask, $sum, $sums)) {
                $minimal[$mask & -$mask][] = $mask;
            }
        }

        $best = [];
        $groups = [];

        foreach (self::best(\count($sums) - 1, $minimal, $best) as $group) {
            $groups[] = array_values(array_filter($values, static fn (int $index): bool => 0 !== ($group & (1 << $index)), \ARRAY_FILTER_USE_KEY));
        }

        return [$groups, true];
    }

    /**
     * The most groups the dice of a mask form, as masks: the highest die of the mask is either left out, or in one of
     * the minimal groups it starts that the mask holds. The answers already found are kept by mask.
     *
     * @param array<int, list<int>> $minimal the minimal groups, by their highest die
     * @param array<int, list<int>> $best    the answers already found, by mask
     *
     * @return list<int>
     */
    private static function best(int $mask, array $minimal, array &$best): array
    {
        if (0 === $mask) {
            return [];
        }

        if (isset($best[$mask])) {
            return $best[$mask];
        }

        $first = $mask & -$mask;
        $answer = self::best($mask ^ $first, $minimal, $best);

        foreach ($minimal[$first] ?? [] as $group) {
            if (($group & $mask) === $group) {
                $groups = [$group, ...self::best($mask ^ $group, $minimal, $best)];

                if (\count($groups) > \count($answer)) {
                    $answer = $groups;
                }
            }
        }

        return $best[$mask] = $answer;
    }

    /**
     * Whether no die can leave the group with the group still reaching the sum.
     *
     * @param array<int, int> $sums
     */
    private static function minimal(int $group, int $sum, array $sums): bool
    {
        for ($bits = $group; 0 !== $bits; $bits &= $bits - 1) {
            if ($sums[$group ^ ($bits & -$bits)] >= $sum) {
                return false;
            }
        }

        return true;
    }

    /**
     * As many groups as one pass finds: a die that reaches the sum alone is a group; otherwise the highest die left
     * starts a group, which the lowest die that completes it closes, or the highest die left grows.
     *
     * @param list<int> $values from the highest
     *
     * @return list<list<int>>
     */
    private static function greedy(array $values, int $sum): array
    {
        $groups = [];

        while ([] !== $values) {
            $group = [array_shift($values)];

            while (array_sum($group) < $sum && [] !== $values) {
                $closing = array_filter($values, static fn (int $value): bool => array_sum($group) + $value >= $sum);
                $key = [] === $closing ? array_key_first($values) : array_key_last($closing);
                $group[] = $values[$key];
                unset($values[$key]);
            }

            if (array_sum($group) >= $sum) {
                $groups[] = $group;
            }
        }

        return $groups;
    }
}
