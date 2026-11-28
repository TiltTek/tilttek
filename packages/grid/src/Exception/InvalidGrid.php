<?php

declare(strict_types=1);

namespace Tilttek\Grid\Exception;

use InvalidArgumentException;

/**
 * A grid, a coordinate or an alignment the library refuses: a cell size that is not positive, cube coordinates that do
 * not add up to zero, two reference cells that cannot fix a grid.
 */
final class InvalidGrid extends InvalidArgumentException implements GridException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
