<?php

declare(strict_types=1);

namespace Tilttek\Dice\Exception;

use InvalidArgumentException;

/**
 * A definition the application gave that the library refuses: a custom die, a face, a notation, limits, a seed.
 */
final class InvalidDefinition extends InvalidArgumentException implements DiceException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
