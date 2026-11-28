<?php

declare(strict_types=1);

namespace Tilttek\Grid\Square;

use RoundingMode;
use Tilttek\Grid\Ratio;

/**
 * How a diagonal step counts on a square grid, the movement rule of the game.
 */
enum DiagonalRule: string
{
    /**
     * No diagonal step: a diagonal costs two orthogonal steps.
     */
    case Orthogonal = 'orthogonal';

    /**
     * Every diagonal step costs one cell, as an orthogonal one does (5-5-5).
     */
    case Uniform = '5-5-5';

    /**
     * Diagonal steps cost one cell, then two, then one, in turn (5-10-5).
     */
    case Alternating = '5-10-5';

    /**
     * The straight distance between the centres, rounded to whole cells.
     */
    case Euclidean = 'euclidean';

    /**
     * The cost, in cells, of a move of dx columns and dy rows.
     */
    public function cells(int $dx, int $dy, RoundingMode $rounding = RoundingMode::HalfAwayFromZero): int
    {
        $dx = abs($dx);
        $dy = abs($dy);

        return match ($this) {
            self::Orthogonal => $dx + $dy,
            self::Uniform => max($dx, $dy),
            self::Alternating => max($dx, $dy) + intdiv(min($dx, $dy), 2),
            self::Euclidean => Ratio::of($dx * $dx + $dy * $dy)->sqrtRounded($rounding),
        };
    }
}
