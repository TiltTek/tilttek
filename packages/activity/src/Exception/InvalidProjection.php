<?php

declare(strict_types=1);

namespace Tilttek\Activity\Exception;

use LogicException;

/**
 * A projection whose name is not a projection name, or that another registered projection already has: its
 * checkpoint would be shared, and each would skip what the other read.
 */
final class InvalidProjection extends LogicException implements ActivityException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
