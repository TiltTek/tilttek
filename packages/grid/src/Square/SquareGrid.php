<?php

declare(strict_types=1);

namespace Tilttek\Grid\Square;

use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;

/**
 * Where the cells of a square grid lie on the image of a map: the top-left corner of the cell (0, 0) and the size of a
 * cell, in pixels, exact. Finds the cell under a position, the centre of a cell, and snaps a position to a centre or
 * a corner.
 */
final readonly class SquareGrid
{
    /**
     * @throws InvalidGrid when a size is not positive
     */
    public function __construct(public Point $origin, public Ratio $cellWidth, public Ratio $cellHeight)
    {
        if (!$cellWidth->isPositive() || !$cellHeight->isPositive()) {
            throw InvalidGrid::because('A square cell has a positive width and a positive height.');
        }
    }

    /**
     * A grid of square cells as wide as the pixels per cell given — the DPI of a printed map of one-inch squares.
     *
     * @throws InvalidGrid when the pixels per cell are not positive
     */
    public static function ofPixelsPerCell(int $pixelsPerCell, ?Point $origin = null): self
    {
        return new self($origin ?? Point::of(0, 0), Ratio::of($pixelsPerCell), Ratio::of($pixelsPerCell));
    }

    /**
     * The grid of a scanned map from the centres of two cells clicked on its image, which differ in column and in row:
     * the farther apart, the more exact.
     *
     * @throws InvalidGrid when the cells share a column or a row, or the sizes found are not positive
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public static function aligned(Point $first, Cell $firstCell, Point $second, Cell $secondCell): self
    {
        $columns = $secondCell->x - $firstCell->x;
        $rows = $secondCell->y - $firstCell->y;
        if (0 === $columns || 0 === $rows) {
            throw InvalidGrid::because('Two reference cells differ in column and in row.');
        }

        $width = $second->x->minus($first->x)->dividedBy(Ratio::of($columns));
        $height = $second->y->minus($first->y)->dividedBy(Ratio::of($rows));
        if (!$width->isPositive() || !$height->isPositive()) {
            throw InvalidGrid::because('The reference cells give a cell size that is not positive: check their coordinates.');
        }

        $origin = $first->plus(
            $width->times(Ratio::of(2 * $firstCell->x + 1, 2))->negated(),
            $height->times(Ratio::of(2 * $firstCell->y + 1, 2))->negated(),
        );

        return new self($origin, $width, $height);
    }

    /**
     * The cell under a position; a position on a side belongs to the cell to its right or below it.
     *
     * @throws OutOfBounds when the cell is out of bounds
     */
    public function cellAt(Point $point): Cell
    {
        return new Cell(
            $point->x->minus($this->origin->x)->dividedBy($this->cellWidth)->floor(),
            $point->y->minus($this->origin->y)->dividedBy($this->cellHeight)->floor(),
        );
    }

    /**
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function centerOf(Cell $cell): Point
    {
        return $this->origin->plus(
            $this->cellWidth->times(Ratio::of(2 * $cell->x + 1, 2)),
            $this->cellHeight->times(Ratio::of(2 * $cell->y + 1, 2)),
        );
    }

    /**
     * The centre of the cell under a position: where a token dropped there lands.
     *
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function snapToCenter(Point $point): Point
    {
        return $this->centerOf($this->cellAt($point));
    }

    /**
     * The corner of a cell nearest a position: where a token as large as four cells lands.
     *
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function snapToCorner(Point $point): Point
    {
        $column = $point->x->minus($this->origin->x)->dividedBy($this->cellWidth)->round();
        $row = $point->y->minus($this->origin->y)->dividedBy($this->cellHeight)->round();

        return $this->origin->plus($this->cellWidth->times(Ratio::of($column)), $this->cellHeight->times(Ratio::of($row)));
    }
}
