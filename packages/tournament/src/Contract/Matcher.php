<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Contract;

/**
 * Pairs every vertex of a complete graph with another, at the lowest total cost it finds.
 *
 * @internal
 */
interface Matcher
{
    /**
     * @param list<list<int>> $costs the cost of each pair, `$costs[$i][$j]` for `$i < $j`; an even number of vertices
     *
     * @return list<array{int, int}> each pair with its smaller vertex first, by smaller vertex
     */
    public function match(array $costs): array;
}
