<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Exception;

use OutOfBoundsException;

/**
 * A recipe the book does not hold.
 */
final class RecipeNotFound extends OutOfBoundsException implements CraftingException
{
    public static function named(string $code): self
    {
        return new self(\sprintf('The recipe book holds no recipe "%s".', $code));
    }
}
