<?php

declare(strict_types=1);

namespace Tilttek\Points\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: an account, a currency, a lifetime, a movement key, an amount, a rule, a cap, a read or
 * a movement that does not hold together.
 */
final class InvalidPoints extends InvalidArgumentException implements PointsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
