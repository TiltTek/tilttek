<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

/**
 * The bounds of what inventories hold, so that every act and every read stays small: the inventories an owner holds —
 * 20 by default, 1 to 1000 —, the items of an inventory — 1000 by default, 1 to 10,000 —, how deep containers nest — 4
 * by default, 1 to 8 — and the log entries an inventory keeps, the oldest dropped first — 1000 by default, 1 to
 * 100,000.
 */
final readonly class Limits
{
    public const int DEFAULT_INVENTORIES_PER_OWNER = 20;

    public const int MAX_INVENTORIES_PER_OWNER = 1000;

    public const int DEFAULT_ITEMS_PER_INVENTORY = 1000;

    public const int MAX_ITEMS_PER_INVENTORY = 10_000;

    public const int DEFAULT_CONTAINER_DEPTH = 4;

    public const int MAX_CONTAINER_DEPTH = 8;

    public const int DEFAULT_LOG_PER_INVENTORY = 1000;

    public const int MAX_LOG_PER_INVENTORY = 100_000;

    public function __construct(
        public int $inventoriesPerOwner = self::DEFAULT_INVENTORIES_PER_OWNER,
        public int $itemsPerInventory = self::DEFAULT_ITEMS_PER_INVENTORY,
        public int $containerDepth = self::DEFAULT_CONTAINER_DEPTH,
        public int $logPerInventory = self::DEFAULT_LOG_PER_INVENTORY,
    ) {
        Text::between($inventoriesPerOwner, 1, self::MAX_INVENTORIES_PER_OWNER, 'The limit of inventories per owner');
        Text::between($itemsPerInventory, 1, self::MAX_ITEMS_PER_INVENTORY, 'The limit of items per inventory');
        Text::between($containerDepth, 1, self::MAX_CONTAINER_DEPTH, 'The limit of container depth');
        Text::between($logPerInventory, 1, self::MAX_LOG_PER_INVENTORY, 'The limit of log entries per inventory');
    }
}
