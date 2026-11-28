<?php

declare(strict_types=1);

namespace Tilttek\Grid;

use RoundingMode;
use Tilttek\Grid\Exception\OutOfBounds;

/**
 * A position on the image of a map, in pixels from its top-left corner, x to the right and y down, kept exact.
 */
final readonly class Point
{
    public function __construct(public Ratio $x, public Ratio $y)
    {
    }

    public static function of(int $x, int $y): self
    {
        return new self(Ratio::of($x), Ratio::of($y));
    }

    /**
     * @throws OutOfBounds when the result is larger than exact arithmetic allows
     */
    public function plus(Ratio $dx, Ratio $dy): self
    {
        return new self($this->x->plus($dx), $this->y->plus($dy));
    }

    public function equals(self $other): bool
    {
        return $this->x->equals($other->x) && $this->y->equals($other->y);
    }

    /**
     * The pixel this position rounds to, to draw it.
     *
     * @return array{int, int}
     */
    public function rounded(RoundingMode $mode = RoundingMode::HalfAwayFromZero): array
    {
        return [$this->x->round($mode), $this->y->round($mode)];
    }

    /**
     * The square of the distance to another position, exact.
     *
     * @throws OutOfBounds when the result is larger than exact arithmetic allows
     */
    public function squaredDistanceTo(self $other): Ratio
    {
        $dx = $other->x->minus($this->x);
        $dy = $other->y->minus($this->y);

        return $dx->times($dx)->plus($dy->times($dy));
    }
}
