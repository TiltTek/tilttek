<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Contract;

use Tilttek\Inventory\Inventory;
use Tilttek\Inventory\Item;
use Tilttek\Inventory\LogEntry;

/**
 * Where the inventories, their items and their logs are kept. Inventories, owners and carriers are named by their
 * references written `kind:identifier`; items by their keys inside their inventory.
 *
 * The library checks an inventory's limits, quantities, containers and loads before it writes: an application that
 * serves concurrent requests runs each act of `Inventories` in a transaction, or under a lock on the inventory — on
 * both for a transfer —, so that two requests never take the same arrows. An implementation keeps no state between two
 * requests beside its storage.
 */
interface InventoryStore
{
    public function inventory(string $inventory): ?Inventory;

    public function saveInventory(Inventory $inventory): void;

    /**
     * Removes the inventory with its items and its log.
     */
    public function removeInventory(string $inventory): void;

    /**
     * The owner's inventories, the earliest opened first, ties in the order of their references.
     *
     * @return list<Inventory>
     */
    public function inventoriesOf(string $owner): array;

    public function item(string $inventory, string $key): ?Item;

    /**
     * The inventory's items, the earliest added first, ties in the order of their keys.
     *
     * @return list<Item>
     */
    public function items(string $inventory): array;

    public function countItems(string $inventory): int;

    /**
     * Writes the item, replacing the item of the same key in that inventory.
     */
    public function saveItem(Item $item): void;

    public function removeItem(string $inventory, string $key): void;

    /**
     * The items placed directly on the carrier, in every inventory, in the order `items()` reads them, inventory after
     * inventory in the order of their references.
     *
     * @return list<Item>
     */
    public function carriedBy(string $carrier): array;

    /**
     * Adds the entry to its inventory's log, then drops the oldest entries beyond `$keep`.
     */
    public function appendLog(LogEntry $entry, int $keep): void;

    /**
     * The newest `$limit` entries of the inventory's log, the newest first; entries of the same moment, the last
     * written first.
     *
     * @return list<LogEntry>
     */
    public function log(string $inventory, int $limit): array;

    /**
     * Rewrites every entry that names the reference, in every log, as `LogEntry::without()` gives it.
     */
    public function eraseFromLog(string $reference): void;
}
