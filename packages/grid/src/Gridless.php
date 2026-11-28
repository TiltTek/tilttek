<?php

declare(strict_types=1);

namespace Tilttek\Grid;

use RoundingMode;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;

/**
 * A map without a grid: positions stay where they are put, and a distance is measured in the units of the map — feet,
 * metres, squares — from the number of pixels one unit spans, rounded to a whole number without a floating-point step.
 */
final readonly class Gridless
{
    /**
     * @throws InvalidGrid when the pixels per unit are not positive
     */
    public function __construct(public Ratio $pixelsPerUnit, public RoundingMode $rounding = RoundingMode::HalfAwayFromZero)
    {
        if (!$pixelsPerUnit->isPositive()) {
            throw InvalidGrid::because('A unit spans a positive number of pixels.');
        }
    }

    /**
     * The straight distance between two positions, in whole units.
     *
     * @throws OutOfBounds when the computation is larger than exact arithmetic allows
     */
    public function distance(Point $from, Point $to): int
    {
        return $from->squaredDistanceTo($to)->dividedBy($this->pixelsPerUnit->times($this->pixelsPerUnit))->sqrtRounded($this->rounding);
    }
}
