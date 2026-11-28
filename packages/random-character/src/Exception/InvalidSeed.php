<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Exception;

use InvalidArgumentException;

/**
 * A seed that cannot replay a character: empty, not UTF-8, or too long.
 */
final class InvalidSeed extends InvalidArgumentException implements RandomCharacterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
