<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Swiss;

use Override;
use Tilttek\Tournament\Contract\Matcher;

/**
 * The perfect matching of the lowest total cost, exactly: a dynamic programme over the sets of vertices left, which
 * pairs the first vertex left with each other in turn. Its time grows as 2ⁿ, so it is kept to small groups: 16 vertices
 * by default, 20 at most. Of two matchings of the same cost, the one that pairs the first vertex with the lowest one
 * wins, so the result is the same each time.
 *
 * @internal
 */
final readonly class ExactMatcher implements Matcher
{
    #[Override]
    public function match(array $costs): array
    {
        $count = \count($costs);
        $full = (1 << $count) - 1;
        /** @var array<int, int> $best */
        $best = [$full => 0];
        /** @var array<int, int> $choice */
        $choice = [];
        $this->solve(0, $full, $costs, $best, $choice);
        $pairs = [];
        $mask = 0;

        while ($mask !== $full) {
            $first = $this->firstFree($mask);
            $second = $choice[$mask];
            $pairs[] = [$first, $second];
            $mask |= (1 << $first) | (1 << $second);
        }

        return $pairs;
    }

    /**
     * @param list<list<int>> $costs
     * @param array<int, int> $best   the lowest cost to pair the vertices left, by the set of those paired
     * @param array<int, int> $choice the partner of the first vertex left in that lowest cost, by the same set
     */
    private function solve(int $mask, int $full, array $costs, array &$best, array &$choice): int
    {
        if (isset($best[$mask])) {
            return $best[$mask];
        }

        $first = $this->firstFree($mask);
        $lowest = \PHP_INT_MAX;

        for ($second = $first + 1, $count = \count($costs); $second < $count; ++$second) {
            if (0 !== ($mask & (1 << $second))) {
                continue;
            }

            $cost = $costs[$first][$second] + $this->solve($mask | (1 << $first) | (1 << $second), $full, $costs, $best, $choice);

            if ($cost < $lowest) {
                $lowest = $cost;
                $choice[$mask] = $second;
            }
        }

        return $best[$mask] = $lowest;
    }

    private function firstFree(int $mask): int
    {
        $vertex = 0;

        while (0 !== ($mask & (1 << $vertex))) {
            ++$vertex;
        }

        return $vertex;
    }
}
