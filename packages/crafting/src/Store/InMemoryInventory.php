<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Store;

use Phil\Resource\ResourceReference;
use Tilttek\Crafting\Contract\Inventory;
use Tilttek\Crafting\Craft;
use Tilttek\Crafting\Exception\CraftRefused;
use Tilttek\Crafting\Holding;
use Tilttek\Crafting\Text;

/**
 * The holders' items in memory, for tests and for applications that rebuild them at each start. The tags of each item
 * are given once, for every holder.
 */
final class InMemoryInventory implements Inventory
{
    /** @var array<string, array<string, int>> the quantity of each item, by holder */
    private array $items = [];

    /** @var array<string, Craft> */
    private array $crafts = [];

    /**
     * @param array<string, list<string>> $tags the tags of each item
     */
    public function __construct(
        private readonly array $tags = [],
    ) {
    }

    /**
     * Gives a holder items.
     */
    public function put(ResourceReference $holder, string $item, int $quantity = 1): void
    {
        Text::code($item, 'an item');
        Text::quantity($quantity, \sprintf('"%s" put in an inventory', $item), Holding::MAX_QUANTITY);
        $this->items[$holder->toString()][$item] = ($this->items[$holder->toString()][$item] ?? 0) + $quantity;
    }

    /**
     * How many of an item a holder has.
     */
    public function quantity(ResourceReference $holder, string $item): int
    {
        return $this->items[$holder->toString()][$item] ?? 0;
    }

    public function holdings(ResourceReference $holder): iterable
    {
        $items = $this->items[$holder->toString()] ?? [];
        ksort($items, \SORT_STRING);
        $holdings = [];

        foreach ($items as $item => $quantity) {
            $holdings[] = new Holding($item, $quantity, $this->tags[$item] ?? []);
        }

        return $holdings;
    }

    public function find(string $key): ?Craft
    {
        return $this->crafts[$key] ?? null;
    }

    public function apply(Craft $craft): bool
    {
        if (isset($this->crafts[$craft->key])) {
            return false;
        }

        $holder = $craft->holder->toString();
        $items = $this->items[$holder] ?? [];

        $needed = [];

        foreach ([...$craft->consumed, ...$craft->used] as $item) {
            $needed[$item->item] = ($needed[$item->item] ?? 0) + $item->quantity;
        }

        foreach ($needed as $item => $quantity) {
            $held = $items[$item] ?? 0;

            if ($held < $quantity) {
                throw CraftRefused::because(\sprintf('The craft "%s" needs %d × "%s"; the holder has %d.', $craft->key, $quantity, $item, $held));
            }
        }

        foreach ($craft->consumed as $consumed) {
            $items[$consumed->item] -= $consumed->quantity;
        }

        foreach ([...$craft->returned, ...$craft->produced] as $added) {
            $items[$added->item] = ($items[$added->item] ?? 0) + $added->quantity;
        }

        $this->items[$holder] = array_filter($items, static fn (int $quantity): bool => $quantity > 0);
        $this->crafts[$craft->key] = $craft;

        return true;
    }

    /**
     * Removes what the inventory kept of a holder: their items and their crafts.
     */
    public function forget(ResourceReference $holder): void
    {
        unset($this->items[$holder->toString()]);
        $this->crafts = array_filter($this->crafts, static fn (Craft $craft): bool => !$craft->holder->equals($holder));
    }
}
