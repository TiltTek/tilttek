<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Internal;

use SplMinHeap;

/**
 * A minimum-cost perfect assignment of rows to columns on a sparse cost matrix, by successive shortest augmenting
 * paths: Dijkstra on reduced costs, with one potential per column, after a greedy start that gives each row its
 * cheapest column when that column is free.
 *
 * Each augmentation is O(E log V), so the whole is O(V · E log V) at worst, with V rows and E edges; in a trade, most
 * rows take their cheapest column at the start, and most paths are short.
 *
 * Ties go to the smaller column index: the caller orders the rows and columns to break ties as it wants. An instance
 * lives for one call of solve().
 *
 * @internal
 */
final class MinCostAssignment
{
    /**
     * The column of each assigned row.
     *
     * @var array<int, int>
     */
    private array $columnOf = [];

    /**
     * The row each assigned column is taken by, and the cost of that edge.
     *
     * @var array<int, array{int, int}>
     */
    private array $rowOf = [];

    /**
     * The potential of each column whose potential moved; the others are at 0.
     *
     * @var array<int, int>
     */
    private array $potential = [];

    /**
     * @param list<non-empty-list<array{int, int}>> $edges
     */
    private function __construct(private readonly array $edges)
    {
    }

    /**
     * @param list<non-empty-list<array{int, int}>> $edges for each row, its allowed columns and their costs, [column,
     *                                                     cost], with non-negative costs, sorted by column; a perfect
     *                                                     assignment must exist, as when every row may take the
     *                                                     column of its own index
     *
     * @return list<int> the column of each row
     */
    public static function solve(array $edges): array
    {
        $assignment = new self($edges);
        $assignment->start();

        foreach (array_keys($edges) as $row) {
            if (!isset($assignment->columnOf[$row])) {
                $assignment->augment($row);
            }
        }

        ksort($assignment->columnOf);

        return array_values($assignment->columnOf);
    }

    /**
     * Gives each row its cheapest column, the first of equal cost, when no row before took it.
     */
    private function start(): void
    {
        foreach ($this->edges as $row => $columns) {
            $best = $columns[0];

            foreach ($columns as $edge) {
                if ($edge[1] < $best[1]) {
                    $best = $edge;
                }
            }

            if (!isset($this->rowOf[$best[0]])) {
                $this->assign($row, $best[0], $best[1]);
            }
        }
    }

    /**
     * Finds the shortest path, in reduced costs, from the free row to a free column, moves the potentials of the
     * columns it settled, and assigns along the path.
     */
    private function augment(int $start): void
    {
        /** @var array<int, int> $distance */
        $distance = [];
        /** @var array<int, array{int, int}> $through the row each column was reached from, and the cost of that edge */
        $through = [];
        /** @var array<int, true> $settled */
        $settled = [];
        $heap = new SplMinHeap();
        $this->relax($start, 0, 0, $settled, $distance, $through, $heap);

        while (true) {
            /** @var array{int, int} $top */
            $top = $heap->extract();
            [$length, $column] = $top;

            if (isset($settled[$column]) || $length > $distance[$column]) {
                continue;
            }

            $settled[$column] = true;
            $taken = $this->rowOf[$column] ?? null;

            if (null === $taken) {
                break;
            }

            $this->relax($taken[0], $length, $taken[1] - ($this->potential[$column] ?? 0), $settled, $distance, $through, $heap);
        }

        foreach (array_keys($settled) as $settledColumn) {
            $this->potential[$settledColumn] = ($this->potential[$settledColumn] ?? 0) + $distance[$settledColumn] - $length;
        }

        do {
            [$row, $cost] = $through[$column];
            $freed = $this->columnOf[$row] ?? null;
            $this->assign($row, $column, $cost);
            $column = $freed;
        } while (null !== $column);
    }

    /**
     * Reaches the columns of the row from the length it was reached at, in reduced costs.
     *
     * @param array<int, true>            $settled
     * @param array<int, int>             $distance
     * @param array<int, array{int, int}> $through
     * @param SplMinHeap<array{int, int}> $heap
     */
    private function relax(int $row, int $length, int $rowPotential, array $settled, array &$distance, array &$through, SplMinHeap $heap): void
    {
        foreach ($this->edges[$row] as [$column, $cost]) {
            if (isset($settled[$column])) {
                continue;
            }

            $reached = $length + $cost - ($this->potential[$column] ?? 0) - $rowPotential;

            if (!isset($distance[$column]) || $reached < $distance[$column]) {
                $distance[$column] = $reached;
                $through[$column] = [$row, $cost];
                $heap->insert([$reached, $column]);
            }
        }
    }

    private function assign(int $row, int $column, int $cost): void
    {
        $this->columnOf[$row] = $column;
        $this->rowOf[$column] = [$row, $cost];
    }
}
