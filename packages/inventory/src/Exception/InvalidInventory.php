<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a name, a key, a reason, a quantity, a burden, a container, a carrying rule, a capacity
 * or a limit out of bounds.
 */
final class InvalidInventory extends InvalidArgumentException implements InventoryException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
