<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards\Exception;

use InvalidArgumentException;

/**
 * A value of an encounter, a combatant or a call is out of its shape or its bounds.
 */
final class InvalidEncounter extends InvalidArgumentException implements InitiativeException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
