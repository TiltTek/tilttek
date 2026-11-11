<?php

declare(strict_types=1);

namespace Tilttek\Collections\Exception;

use InvalidArgumentException;

/**
 * A code, a text, a rarity, an item, a set, a pack, a catalog, a key, a day or a seed is refused.
 */
final class InvalidCollection extends InvalidArgumentException implements CollectionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    public static function name(string $what, string $name): self
    {
        return new self(\sprintf('"%s" is not a %s code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $name, $what));
    }
}
