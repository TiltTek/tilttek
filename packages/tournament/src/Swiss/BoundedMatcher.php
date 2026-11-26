<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Swiss;

use Override;
use Tilttek\Tournament\Contract\Matcher;

/**
 * A perfect matching of a low total cost, in bounded time, for groups too large for the exact one: each vertex in turn
 * takes the cheapest vertex left, then pairs of pairs exchange partners while an exchange lowers the cost, for a bounded
 * number of passes. Each pass reads every pair of pairs once, so the time grows as n² by pass. The result is the same
 * each time; it is not always the lowest cost, and the documentation says when it misses it.
 *
 * @internal
 */
final readonly class BoundedMatcher implements Matcher
{
    public function __construct(
        private int $passes = 20,
    ) {
    }

    #[Override]
    public function match(array $costs): array
    {
        $free = array_keys($costs);
        $pairs = [];

        while ([] !== $free) {
            $first = array_shift($free);
            $nearest = null;

            foreach ($free as $position => $second) {
                if (null === $nearest || $costs[$first][$second] < $costs[$first][$free[$nearest]]) {
                    $nearest = $position;
                }
            }

            if (null === $nearest) {
                break;
            }

            $pairs[] = [$first, $free[$nearest]];
            array_splice($free, $nearest, 1);
        }

        for ($pass = 0; $pass < $this->passes && $this->improve($pairs, $costs); ++$pass) {
            // Each pass exchanges partners while it lowers the cost.
        }

        usort($pairs, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        return $pairs;
    }

    /**
     * One pass of exchanges between pairs of pairs: whether one lowered the cost.
     *
     * @param list<array{int, int}> $pairs
     * @param list<list<int>>       $costs
     */
    private function improve(array &$pairs, array $costs): bool
    {
        $improved = false;
        $count = \count($pairs);

        for ($p = 0; $p < $count; ++$p) {
            for ($q = $p + 1; $q < $count; ++$q) {
                [$a, $b] = $pairs[$p];
                [$c, $d] = $pairs[$q];
                $now = $this->cost($costs, $a, $b) + $this->cost($costs, $c, $d);
                $crossed = $this->cost($costs, $a, $c) + $this->cost($costs, $b, $d);
                $swapped = $this->cost($costs, $a, $d) + $this->cost($costs, $b, $c);

                if ($crossed < $now && $crossed <= $swapped) {
                    $pairs[$p] = $this->pair($a, $c);
                    $pairs[$q] = $this->pair($b, $d);
                    $improved = true;
                } elseif ($swapped < $now) {
                    $pairs[$p] = $this->pair($a, $d);
                    $pairs[$q] = $this->pair($b, $c);
                    $improved = true;
                }
            }
        }

        return $improved;
    }

    /**
     * @param list<list<int>> $costs
     */
    private function cost(array $costs, int $a, int $b): int
    {
        return $costs[min($a, $b)][max($a, $b)];
    }

    /**
     * @return array{int, int}
     */
    private function pair(int $a, int $b): array
    {
        return [min($a, $b), max($a, $b)];
    }
}
