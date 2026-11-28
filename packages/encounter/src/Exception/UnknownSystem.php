<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Exception;

use RuntimeException;

/**
 * No rules of difficulty are declared for the game system.
 */
final class UnknownSystem extends RuntimeException implements EncounterException
{
    public static function of(string $system): self
    {
        return new self(\sprintf('No rules of difficulty are declared for the game system %s.', $system));
    }
}
