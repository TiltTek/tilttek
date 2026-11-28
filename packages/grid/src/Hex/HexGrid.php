<?php

declare(strict_types=1);

namespace Tilttek\Grid\Hex;

use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;

/**
 * Where the hexagons of a grid lie on the image of a map: their orientation, the centre of the hexagon (0, 0), and the
 * width and height of a hexagon from side to side or from point to point, in pixels, exact. A hexagon drawn stretched
 * is measured as it is drawn. Finds the hexagon under a position and the centre of a hexagon, without a floating-point
 * step.
 */
final readonly class HexGrid
{
    /**
     * @throws InvalidGrid when a size is not positive
     */
    public function __construct(public Orientation $orientation, public Point $origin, public Ratio $width, public Ratio $height)
    {
        if (!$width->isPositive() || !$height->isPositive()) {
            throw InvalidGrid::because('A hexagon has a positive width and a positive height.');
        }
    }

    /**
     * The grid of a scanned map from the centres of two hexagons clicked on its image: two hexagons in different rows
     * (pointy) or columns (flat), and not straight above one another (pointy) or beside one another (flat).
     *
     * @throws InvalidGrid when the two hexagons cannot fix both sizes, or the sizes found are not positive
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public static function aligned(Orientation $orientation, Point $first, Axial $firstHex, Point $second, Axial $secondHex): self
    {
        $dq = $secondHex->q - $firstHex->q;
        $dr = $secondHex->r - $firstHex->r;
        $dx = $second->x->minus($first->x);
        $dy = $second->y->minus($first->y);

        [$along, $across] = Orientation::Pointy === $orientation ? [$dr, 2 * $dq + $dr] : [$dq, 2 * $dr + $dq];
        if (0 === $along || 0 === $across) {
            throw InvalidGrid::because('Two reference hexagons lie in different rows or columns, and not in a straight line across them.');
        }

        [$width, $height] = Orientation::Pointy === $orientation
            ? [$dx->times(Ratio::of(2, $across)), $dy->times(Ratio::of(4, 3 * $along))]
            : [$dx->times(Ratio::of(4, 3 * $along)), $dy->times(Ratio::of(2, $across))];
        if (!$width->isPositive() || !$height->isPositive()) {
            throw InvalidGrid::because('The reference hexagons give a size that is not positive: check their coordinates.');
        }

        $grid = new self($orientation, Point::of(0, 0), $width, $height);
        $offset = $grid->centerOf($firstHex);

        return new self($orientation, $first->plus($offset->x->negated(), $offset->y->negated()), $width, $height);
    }

    /**
     * The hexagon under a position.
     *
     * @throws OutOfBounds when the hexagon is out of bounds
     */
    public function cellAt(Point $point): Axial
    {
        $x = $point->x->minus($this->origin->x);
        $y = $point->y->minus($this->origin->y);
        $half = Ratio::of(1, 2);

        if (Orientation::Pointy === $this->orientation) {
            $r = $y->times(Ratio::of(4, 3))->dividedBy($this->height);
            $q = $x->dividedBy($this->width)->minus($r->times($half));
        } else {
            $q = $x->times(Ratio::of(4, 3))->dividedBy($this->width);
            $r = $y->dividedBy($this->height)->minus($q->times($half));
        }

        return Cube::nearestTo($q, $r)->toAxial();
    }

    /**
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function centerOf(Axial $hex): Point
    {
        if (Orientation::Pointy === $this->orientation) {
            return $this->origin->plus(
                $this->width->times(Ratio::of(2 * $hex->q + $hex->r, 2)),
                $this->height->times(Ratio::of(3 * $hex->r, 4)),
            );
        }

        return $this->origin->plus(
            $this->width->times(Ratio::of(3 * $hex->q, 4)),
            $this->height->times(Ratio::of(2 * $hex->r + $hex->q, 2)),
        );
    }

    /**
     * The centre of the hexagon under a position: where a token dropped there lands.
     *
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function snapToCenter(Point $point): Point
    {
        return $this->centerOf($this->cellAt($point));
    }
}
