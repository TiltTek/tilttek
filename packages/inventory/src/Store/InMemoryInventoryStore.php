<?php

declare(strict_types=1);

namespace Tilttek\Inventory\Store;

use Tilttek\Inventory\Contract\InventoryStore;
use Tilttek\Inventory\Inventory;
use Tilttek\Inventory\Item;
use Tilttek\Inventory\LogEntry;

/**
 * The inventories kept in memory, for tests and for an application that rebuilds them at each start.
 */
final class InMemoryInventoryStore implements InventoryStore
{
    /** @var array<string, Inventory> */
    private array $inventories = [];

    /** @var array<string, array<string, Item>> by inventory, then key */
    private array $items = [];

    /** @var array<string, list<LogEntry>> by inventory, the oldest first */
    private array $logs = [];

    public function inventory(string $inventory): ?Inventory
    {
        return $this->inventories[$inventory] ?? null;
    }

    public function saveInventory(Inventory $inventory): void
    {
        $this->inventories[$inventory->reference->toString()] = $inventory;
    }

    public function removeInventory(string $inventory): void
    {
        unset($this->inventories[$inventory], $this->items[$inventory], $this->logs[$inventory]);
    }

    public function inventoriesOf(string $owner): array
    {
        $found = array_filter($this->inventories, static fn (Inventory $inventory): bool => $inventory->owner->toString() === $owner);
        usort($found, static function (Inventory $a, Inventory $b): int {
            $byTime = $a->openedAt <=> $b->openedAt;

            return 0 !== $byTime ? $byTime : strcmp($a->reference->toString(), $b->reference->toString());
        });

        return $found;
    }

    public function item(string $inventory, string $key): ?Item
    {
        return $this->items[$inventory][$key] ?? null;
    }

    public function items(string $inventory): array
    {
        return $this->sorted($this->items[$inventory] ?? []);
    }

    public function countItems(string $inventory): int
    {
        return \count($this->items[$inventory] ?? []);
    }

    public function saveItem(Item $item): void
    {
        $this->items[$item->inventory->toString()][$item->key] = $item;
    }

    public function removeItem(string $inventory, string $key): void
    {
        unset($this->items[$inventory][$key]);
    }

    public function carriedBy(string $carrier): array
    {
        $inventories = array_keys($this->items);
        sort($inventories, \SORT_STRING);
        $found = [];

        foreach ($inventories as $inventory) {
            foreach ($this->sorted($this->items[$inventory]) as $item) {
                if ($item->place->carrier?->toString() === $carrier) {
                    $found[] = $item;
                }
            }
        }

        return $found;
    }

    public function appendLog(LogEntry $entry, int $keep): void
    {
        $log = $this->logs[$entry->inventory] ?? [];
        $log[] = $entry;
        $this->logs[$entry->inventory] = \array_slice($log, -$keep);
    }

    public function log(string $inventory, int $limit): array
    {
        $log = $this->logs[$inventory] ?? [];
        $order = array_keys($log);
        usort($order, static function (int $a, int $b) use ($log): int {
            $byTime = $log[$b]->at <=> $log[$a]->at;

            return 0 !== $byTime ? $byTime : $b <=> $a;
        });

        return array_map(static fn (int $index): LogEntry => $log[$index], \array_slice($order, 0, $limit));
    }

    public function eraseFromLog(string $reference): void
    {
        foreach ($this->logs as $inventory => $log) {
            $this->logs[$inventory] = array_map(static fn (LogEntry $entry): LogEntry => $entry->names($reference) ? $entry->without($reference) : $entry, $log);
        }
    }

    /**
     * @param array<Item> $items
     *
     * @return list<Item>
     */
    private function sorted(array $items): array
    {
        usort($items, static function (Item $a, Item $b): int {
            $byTime = $a->addedAt <=> $b->addedAt;

            return 0 !== $byTime ? $byTime : strcmp($a->key, $b->key);
        });

        return $items;
    }
}
