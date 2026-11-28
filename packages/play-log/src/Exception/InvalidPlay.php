<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Exception;

use InvalidArgumentException;

/**
 * A value of a play, a seat or a query is out of its shape or its bounds.
 */
final class InvalidPlay extends InvalidArgumentException implements PlayLogException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
