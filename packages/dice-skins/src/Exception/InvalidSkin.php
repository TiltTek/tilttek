<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a colour, a key, a label, a texture reference, a number of sides or a skin definition
 * it cannot read, or a skin whose numbers would not stay readable on its faces.
 */
final class InvalidSkin extends InvalidArgumentException implements DiceSkinsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
