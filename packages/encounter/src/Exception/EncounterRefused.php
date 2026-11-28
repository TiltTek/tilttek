<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Exception;

use DomainException;

/**
 * The call is well formed but not allowed on the encounter in its state: by someone other than its game master, on an
 * encounter already launched, on a participant it does not hold, or a difficulty the rules cannot assess.
 */
final class EncounterRefused extends DomainException implements EncounterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
