<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Exception;

use InvalidArgumentException;

/**
 * An army list the library refuses to hold — an identifier, a name, a number out of its bounds — or that does not
 * read from an array or JSON. A list that breaks the catalogue's rules is not refused: the validator reports it.
 */
final class InvalidArmyList extends InvalidArgumentException implements WargameArmyException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
