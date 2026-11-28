<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Exception;

use InvalidArgumentException;

/**
 * A change to a rolled character that it cannot take: a value it does not have, or one out of the library's bounds.
 */
final class InvalidEdit extends InvalidArgumentException implements RandomCharacterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
