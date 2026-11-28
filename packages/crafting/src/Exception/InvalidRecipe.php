<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a code, a tag, a quantity, a recipe, a book, a selection or a key out of bounds.
 */
final class InvalidRecipe extends InvalidArgumentException implements CraftingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
