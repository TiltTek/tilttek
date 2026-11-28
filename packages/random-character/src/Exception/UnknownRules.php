<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Exception;

use OutOfBoundsException;

/**
 * No creation rules have this key in the catalogue.
 */
final class UnknownRules extends OutOfBoundsException implements RandomCharacterException
{
    public static function key(string $key): self
    {
        return new self(\sprintf('No creation rules have the key "%s".', $key));
    }
}
