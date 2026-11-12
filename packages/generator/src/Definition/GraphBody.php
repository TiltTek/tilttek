<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * A map of places joined by passages — the rooms of a dungeon —: a number of nodes, fixed or rolled, each filled by
 * the same field; every node reachable, and a number of extra passages that make loops.
 */
final readonly class GraphBody implements Body
{
    /**
     * @param int|string $nodes a number, or a dice expression rolled for each map
     */
    public function __construct(public int|string $nodes, public int $extraEdges, public Field $cell)
    {
        if (\is_int($nodes) && $nodes < 1) {
            throw new InvalidDefinition('A graph has at least one node.');
        }

        if ($extraEdges < 0) {
            throw new InvalidDefinition('The extra passages of a graph are a whole number of at least 0.');
        }
    }
}
