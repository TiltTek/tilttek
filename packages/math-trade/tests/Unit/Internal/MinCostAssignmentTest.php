<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit\Internal;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\MathTrade\Internal\MinCostAssignment;

#[CoversClass(MinCostAssignment::class)]
final class MinCostAssignmentTest extends TestCase
{
    public function testTakesTheCheapestColumnOfEachRow(): void
    {
        self::assertSame([1, 0], MinCostAssignment::solve([[[0, 5], [1, 1]], [[0, 2], [1, 9]]]));
    }

    public function testGivesTiesToTheSmallerColumn(): void
    {
        self::assertSame([0, 1], MinCostAssignment::solve([[[0, 1], [1, 1]], [[0, 1], [1, 1]]]));
        self::assertSame([1, 0], MinCostAssignment::solve([[[0, 2], [1, 1]], [[0, 1], [1, 1]]]));
    }

    public function testBreaksTiesBetweenAssignmentsOfEqualCostThroughTheCheapestFirstStart(): void
    {
        // [0, 1, 2, 3, 4] and [3, 1, 2, 4, 0] both cost 14: the rows take their cheapest free column first, and the
        // rows left over take the shortest paths.
        $edges = [
            [[0, 3], [1, 2], [2, 4], [3, 1], [4, 2]],
            [[1, 5], [2, 1], [4, 4]],
            [[2, 1]],
            [[2, 3], [3, 4], [4, 3]],
            [[0, 4], [2, 3], [4, 1]],
        ];

        self::assertSame([0, 1, 2, 3, 4], MinCostAssignment::solve($edges));
    }

    public function testMovesARowOffItsCheapestColumnWhenThatIsCheaperOverall(): void
    {
        // Both rows prefer column 0; row 1 has nowhere else, so row 0 moves to column 1 at a cost of 3.
        self::assertSame([1, 0], MinCostAssignment::solve([[[0, 1], [1, 4]], [[0, 2]]]));
    }

    public function testFollowsAPathThroughSeveralRows(): void
    {
        $edges = [
            [[0, 0], [1, 1]],
            [[0, 0], [2, 5]],
            [[0, 0], [1, 1], [3, 1]],
            [[0, 0], [3, 7]],
        ];

        self::assertSame(self::bruteForce($edges)[0], $this->cost($edges, MinCostAssignment::solve($edges)));
    }

    public function testFindsTheMinimumOnRandomMatrices(): void
    {
        $random = new Randomizer(new Xoshiro256StarStar(7));

        for ($trial = 0; $trial < 300; ++$trial) {
            $size = $random->getInt(1, 6);
            $edges = [];

            for ($row = 0; $row < $size; ++$row) {
                $line = [];

                for ($column = 0; $column < $size; ++$column) {
                    if ($column === $row || 0 === $random->getInt(0, 1)) {
                        $line[] = [$column, $random->getInt(0, 20)];
                    }
                }

                self::assertNotSame([], $line);
                $edges[] = $line;
            }

            $assignment = MinCostAssignment::solve($edges);

            self::assertSame(range(0, $size - 1), array_keys($assignment));
            self::assertCount($size, array_unique($assignment));
            self::assertSame(self::bruteForce($edges)[0], $this->cost($edges, $assignment), 'Trial '.$trial);
        }
    }

    /**
     * @param list<list<array{int, int}>> $edges
     * @param list<int>                   $assignment
     */
    private function cost(array $edges, array $assignment): int
    {
        $cost = 0;

        foreach ($assignment as $row => $column) {
            $found = array_values(array_filter($edges[$row], static fn (array $edge): bool => $edge[0] === $column));
            self::assertCount(1, $found, 'A row took a column it has no edge to.');
            $cost += $found[0][1];
        }

        return $cost;
    }

    /**
     * @param list<list<array{int, int}>> $edges
     * @param array<int, true>            $taken
     *
     * @return array{int}
     */
    private static function bruteForce(array $edges, int $row = 0, array $taken = []): array
    {
        if ($row === \count($edges)) {
            return [0];
        }

        $best = \PHP_INT_MAX;

        foreach ($edges[$row] as [$column, $cost]) {
            if (!isset($taken[$column])) {
                $best = min($best, $cost + self::bruteForce($edges, $row + 1, $taken + [$column => true])[0]);
            }
        }

        return [$best];
    }
}
