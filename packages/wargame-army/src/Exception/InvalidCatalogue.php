<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Exception;

use InvalidArgumentException;

/**
 * A catalogue the library refuses: a unit, an option, a detachment or a constraint it cannot take, or a catalogue
 * that does not read from an array or JSON.
 */
final class InvalidCatalogue extends InvalidArgumentException implements WargameArmyException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
