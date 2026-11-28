<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Exception;

use OutOfBoundsException;

/**
 * No inventory holds this reference, or no item this key.
 */
final class UnknownInventory extends OutOfBoundsException implements InventoryException
{
    public static function named(string $reference): self
    {
        return new self(\sprintf('No inventory is known as "%s".', $reference));
    }

    public static function item(string $inventory, string $key): self
    {
        return new self(\sprintf('The inventory "%s" holds no item under the key "%s".', $inventory, $key));
    }
}
