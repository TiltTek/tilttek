<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use DateTimeImmutable;

/**
 * An inventory changed: identifiers only — the inventory, the key of the item it concerns, the reference that acted —
 * and the moment, for the application to relay as a notification or a message on its bus. Names, quantities, places
 * and reasons are never told: the application reads the item or the log when it needs them.
 */
final readonly class InventoryChanged
{
    public function __construct(
        public string $inventory,
        public Change $change,
        public ?string $item,
        public ?string $by,
        public DateTimeImmutable $at,
    ) {
    }
}
