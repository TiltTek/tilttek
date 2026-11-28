<?php

declare(strict_types=1);

namespace Tilttek\Factions\Exception;

use RuntimeException;

/**
 * The call is not allowed: the member is not the roster's game master, or the change goes around the history.
 */
final class RosterRefused extends RuntimeException implements FactionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
