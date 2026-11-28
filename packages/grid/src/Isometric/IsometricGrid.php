<?php

declare(strict_types=1);

namespace Tilttek\Grid\Isometric;

use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;
use Tilttek\Grid\Square\Cell;

/**
 * Where the cells of an isometric grid lie on the image of a map: a square grid drawn as diamonds, x down to the right
 * and y down to the left, from the top corner of the diamond (0, 0) and the width and height of a diamond, in pixels.
 * Its cells, distances and lines are those of a square grid ({@see \Tilttek\Grid\Square\Movement}).
 */
final readonly class IsometricGrid
{
    /**
     * @throws InvalidGrid when a size is not positive
     */
    public function __construct(public Point $origin, public Ratio $cellWidth, public Ratio $cellHeight)
    {
        if (!$cellWidth->isPositive() || !$cellHeight->isPositive()) {
            throw InvalidGrid::because('An isometric cell has a positive width and a positive height.');
        }
    }

    /**
     * The cell under a position; a position on a side belongs to the cell below it.
     *
     * @throws OutOfBounds when the cell is out of bounds
     */
    public function cellAt(Point $point): Cell
    {
        $u = $point->x->minus($this->origin->x)->dividedBy($this->cellWidth);
        $v = $point->y->minus($this->origin->y)->dividedBy($this->cellHeight);

        return new Cell($u->plus($v)->floor(), $v->minus($u)->floor());
    }

    /**
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function centerOf(Cell $cell): Point
    {
        return $this->origin->plus(
            $this->cellWidth->times(Ratio::of($cell->x - $cell->y, 2)),
            $this->cellHeight->times(Ratio::of($cell->x + $cell->y + 1, 2)),
        );
    }

    /**
     * The centre of the diamond under a position.
     *
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function snapToCenter(Point $point): Point
    {
        return $this->centerOf($this->cellAt($point));
    }
}
