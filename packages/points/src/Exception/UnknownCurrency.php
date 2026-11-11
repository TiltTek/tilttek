<?php

declare(strict_types=1);

namespace Tilttek\Points\Exception;

use OutOfBoundsException;

/**
 * A currency asked for by a code none has.
 */
final class UnknownCurrency extends OutOfBoundsException implements PointsException
{
    public static function named(string $code): self
    {
        return new self(\sprintf('No currency of points is named "%s".', $code));
    }
}
