<?php

declare(strict_types=1);

namespace Tilttek\TurnClock\Exception;

use DomainException;

/**
 * The change is well formed but not allowed on a clock in its state: a turn passed by a player not holding it, a clock stopped or not started.
 */
final class ClockRefused extends DomainException implements TurnClockException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
