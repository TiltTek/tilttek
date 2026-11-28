<?php

declare(strict_types=1);

namespace Tilttek\Party\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a name, a key, a text, an amount, a quantity, a role or a limit out of bounds.
 */
final class InvalidParty extends InvalidArgumentException implements PartyException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
