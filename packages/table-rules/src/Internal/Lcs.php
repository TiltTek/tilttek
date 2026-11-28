<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Internal;

/**
 * The longest common subsequence of two lists of strings, as the pairs of positions it matches. The common start and
 * end are matched first; the rest is a table of (n + 1) × (m + 1) lengths, which the callers bound.
 *
 * @internal
 */
final class Lcs
{
    private function __construct()
    {
    }

    /**
     * @param list<string> $a
     * @param list<string> $b
     *
     * @return list<array{int, int}> the matched positions in $a and in $b, in order
     */
    public static function pairs(array $a, array $b): array
    {
        $n = \count($a);
        $m = \count($b);
        $start = 0;
        while ($start < $n && $start < $m && $a[$start] === $b[$start]) {
            ++$start;
        }

        $endA = $n;
        $endB = $m;
        while ($endA > $start && $endB > $start && $a[$endA - 1] === $b[$endB - 1]) {
            --$endA;
            --$endB;
        }

        $pairs = [];
        for ($i = 0; $i < $start; ++$i) {
            $pairs[] = [$i, $i];
        }

        $rows = $endA - $start;
        $columns = $endB - $start;
        // The length of the subsequence common to $a and $b from each pair of positions.
        $length = array_fill(0, $rows + 1, array_fill(0, $columns + 1, 0));
        for ($i = $rows - 1; $i >= 0; --$i) {
            for ($j = $columns - 1; $j >= 0; --$j) {
                $length[$i][$j] = $a[$start + $i] === $b[$start + $j]
                    ? $length[$i + 1][$j + 1] + 1
                    : max($length[$i + 1][$j], $length[$i][$j + 1]);
            }
        }

        $i = 0;
        $j = 0;
        while ($i < $rows && $j < $columns) {
            if ($a[$start + $i] === $b[$start + $j]) {
                $pairs[] = [$start + $i, $start + $j];
                ++$i;
                ++$j;
            } elseif ($length[$i + 1][$j] >= $length[$i][$j + 1]) {
                ++$i;
            } else {
                ++$j;
            }
        }

        for ($k = 0; $k < $n - $endA; ++$k) {
            $pairs[] = [$endA + $k, $endB + $k];
        }

        return $pairs;
    }
}
