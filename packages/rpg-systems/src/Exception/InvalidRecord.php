<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a key, a locale, a text, a source or a dice expression that does not hold together.
 */
final class InvalidRecord extends InvalidArgumentException implements RpgSystemsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
