<?php

declare(strict_types=1);

namespace Tilttek\Grid\Exception;

use RangeException;

/**
 * A computation that would leave the bounds of the library: a coordinate too far from the origin, a line, an area or a
 * field of view too large, a number too large for exact arithmetic.
 */
final class OutOfBounds extends RangeException implements GridException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
