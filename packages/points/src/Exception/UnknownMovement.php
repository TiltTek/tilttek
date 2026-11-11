<?php

declare(strict_types=1);

namespace Tilttek\Points\Exception;

use OutOfBoundsException;

/**
 * A movement asked for by a key the ledger does not hold, or that is not of the kind the operation needs.
 */
final class UnknownMovement extends OutOfBoundsException implements PointsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
