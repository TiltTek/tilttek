<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Exception;

use OutOfBoundsException;

/**
 * A system or one of its records — an attribute, a dice expression, a term, a rule variant, difficulty numbers, a band
 * of difficulty, a party role — asked for by a key the reference does not hold.
 */
final class UnknownRecord extends OutOfBoundsException implements RpgSystemsException
{
    public static function of(string $kind, string $key, string $system = ''): self
    {
        return new self('' === $system
            ? \sprintf('No %s has the key "%s".', $kind, $key)
            : \sprintf('The system "%s" has no %s with the key "%s".', $system, $kind, $key));
    }
}
