<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Exception;

use RuntimeException;

/**
 * A craft that cannot be made: items are missing, the key already made another craft, or the inventory changed under
 * it. Nothing moved in the inventory.
 */
final class CraftRefused extends RuntimeException implements CraftingException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
