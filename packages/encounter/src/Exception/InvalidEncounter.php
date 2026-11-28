<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Exception;

use InvalidArgumentException;

/**
 * A value of a participant, an encounter, a rule of difficulty or a call is out of its shape or its bounds.
 */
final class InvalidEncounter extends InvalidArgumentException implements EncounterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
