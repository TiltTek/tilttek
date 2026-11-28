<?php

declare(strict_types=1);

namespace Tilttek\Grid\Square;

use Closure;
use RoundingMode;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;

/**
 * Distances, lines, areas, rings and fields of view on a square or isometric grid, under a diagonal rule. Every result
 * is a whole number of cells or a list of cells, computed with integers.
 */
final readonly class Movement
{
    /**
     * The scale of the nudge that settles a line passing exactly between two cells: always to the right and down.
     */
    private const int NUDGE = 10;

    public function __construct(public DiagonalRule $rule = DiagonalRule::Uniform, public RoundingMode $rounding = RoundingMode::HalfAwayFromZero)
    {
    }

    /**
     * The distance between two cells, in cells, under the diagonal rule.
     */
    public function distance(Cell $from, Cell $to): int
    {
        return $this->rule->cells($to->x - $from->x, $to->y - $from->y, $this->rounding);
    }

    /**
     * The cells a straight line from the centre of one cell to the centre of another crosses, both ends included, each
     * cell touching the one before by a side or a corner.
     *
     * @return non-empty-list<Cell>
     *
     * @throws OutOfBounds when the line has more than {@see Limits::LINE} steps
     */
    public function line(Cell $from, Cell $to): array
    {
        $cells = [$from];
        foreach ($this->walk($from, $to) as [$x, $y]) {
            $cells[] = new Cell($x, $y);
        }

        return $cells;
    }

    /**
     * Every cell at most a radius away from a centre, under the diagonal rule — the area of an effect —, row by row.
     *
     * @return list<Cell>
     *
     * @throws OutOfBounds when the radius is negative or larger than {@see Limits::RADIUS}
     */
    public function area(Cell $center, int $radius): array
    {
        return $this->select($center, Limits::radius($radius, Limits::RADIUS, 'an area'), static fn (int $distance): bool => $distance <= $radius);
    }

    /**
     * Every cell exactly a radius away from a centre, under the diagonal rule, row by row.
     *
     * @return list<Cell>
     *
     * @throws OutOfBounds when the radius is negative or larger than {@see Limits::RADIUS}
     */
    public function ring(Cell $center, int $radius): array
    {
        return $this->select($center, Limits::radius($radius, Limits::RADIUS, 'a ring'), static fn (int $distance): bool => $distance === $radius);
    }

    /**
     * Every cell of an area a viewer at its centre can see: a cell is seen when no cell strictly between it and the
     * viewer, on the line between their centres, blocks the sight. A cell that blocks is seen itself — a wall is seen,
     * not what lies behind it.
     *
     * @param Closure(Cell): bool $blocksSight whether a cell blocks the sight
     *
     * @return non-empty-list<Cell>
     *
     * @throws OutOfBounds when the radius is negative or larger than {@see Limits::SIGHT}
     */
    public function fieldOfView(Cell $viewer, int $radius, Closure $blocksSight): array
    {
        Limits::radius($radius, Limits::SIGHT, 'a field of view');
        $seen = [$viewer];
        // Each cell is asked once whether it blocks, however many lines cross it.
        $blocks = [];
        foreach ($this->area($viewer, $radius) as $cell) {
            if ($cell->equals($viewer)) {
                continue;
            }

            $steps = $this->walk($viewer, $cell);
            array_pop($steps);
            $clear = true;
            foreach ($steps as [$x, $y]) {
                $key = $x.','.$y;
                if ($blocks[$key] ??= $blocksSight(new Cell($x, $y))) {
                    $clear = false;
                    break;
                }
            }

            if ($clear) {
                $seen[] = $cell;
            }
        }

        return $seen;
    }

    /**
     * The coordinates of the cells of the line between two cells, the first left out, computed with integers only.
     *
     * @return list<array{int, int}>
     *
     * @throws OutOfBounds when the line has more than {@see Limits::LINE} steps
     */
    private function walk(Cell $from, Cell $to): array
    {
        $dx = $to->x - $from->x;
        $dy = $to->y - $from->y;
        $steps = Limits::line(max(abs($dx), abs($dy)));
        $denominator = $steps * self::NUDGE;
        $cells = [];
        for ($step = 1; $step <= $steps; ++$step) {
            $cells[] = [
                Limits::roundHalfUp(($from->x * $steps + $dx * $step) * self::NUDGE + 1, $denominator),
                Limits::roundHalfUp(($from->y * $steps + $dy * $step) * self::NUDGE + 1, $denominator),
            ];
        }

        return $cells;
    }

    /**
     * @param Closure(int): bool $keep
     *
     * @return list<Cell>
     */
    private function select(Cell $center, int $radius, Closure $keep): array
    {
        // Under every rule, a move costs at least its longer side: no cell beyond the square of the radius qualifies.
        $cells = [];
        for ($dy = -$radius; $dy <= $radius; ++$dy) {
            for ($dx = -$radius; $dx <= $radius; ++$dx) {
                if ($keep($this->rule->cells($dx, $dy, $this->rounding))) {
                    $cells[] = $center->plus($dx, $dy);
                }
            }
        }

        return $cells;
    }
}
