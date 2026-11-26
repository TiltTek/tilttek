<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Exception;

use InvalidArgumentException;

/**
 * A value of a clock, a time control, a vacation or a call is out of its shape or its bounds.
 */
final class InvalidClock extends InvalidArgumentException implements TurnClockException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
