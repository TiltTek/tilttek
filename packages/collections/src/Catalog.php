<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;

/**
 * What a site's collection holds: its rarities from the most common to the rarest, its items, its sets and its packs,
 * each by code, every reference between them checked — an item's rarity and sets exist, a pack draws from sets that
 * exist, with weights for rarities that exist, and has something to draw. A set may hold no item yet, while a catalog
 * kept in a database is filled one definition at a time.
 */
final readonly class Catalog
{
    /**
     * @var array<string, Rarity>
     */
    private array $rarities;

    /**
     * @var array<string, Item>
     */
    private array $items;

    /**
     * @var array<string, ItemSet>
     */
    private array $sets;

    /**
     * @var array<string, Pack>
     */
    private array $packs;

    /**
     * @param list<Rarity>  $rarities
     * @param list<Item>    $items
     * @param list<ItemSet> $sets
     * @param list<Pack>    $packs
     */
    public function __construct(array $rarities = [], array $items = [], array $sets = [], array $packs = [])
    {
        $this->rarities = $this->byCode($rarities, 'rarity');
        $this->items = $this->byCode($items, 'item');
        $this->sets = $this->byCode($sets, 'set');
        $this->packs = $this->byCode($packs, 'pack');

        foreach ($this->items as $item) {
            $this->rarity($item->rarity);

            foreach ($item->sets as $set) {
                $this->set($set);
            }
        }

        foreach ($this->packs as $pack) {
            $this->odds($pack->sets, $pack->weights);
        }
    }

    /**
     * @return list<Rarity>
     */
    public function rarities(): array
    {
        return array_values($this->rarities);
    }

    public function rarity(string $code): Rarity
    {
        return $this->rarities[$code] ?? throw NotFound::of('rarity', $code);
    }

    /**
     * @return list<Item>
     */
    public function items(): array
    {
        return array_values($this->items);
    }

    public function item(string $code): Item
    {
        return $this->items[$code] ?? throw NotFound::of('item', $code);
    }

    /**
     * @return list<ItemSet>
     */
    public function sets(): array
    {
        return array_values($this->sets);
    }

    public function set(string $code): ItemSet
    {
        return $this->sets[$code] ?? throw NotFound::of('set', $code);
    }

    /**
     * The items of a set, in the catalog's order.
     *
     * @return list<Item>
     */
    public function itemsOf(string $set): array
    {
        $this->set($set);

        return array_values(array_filter($this->items, static fn (Item $item): bool => $item->belongsTo($set)));
    }

    /**
     * @return list<Pack>
     */
    public function packs(): array
    {
        return array_values($this->packs);
    }

    public function pack(string $code): Pack
    {
        return $this->packs[$code] ?? throw NotFound::of('pack', $code);
    }

    /**
     * The odds of a draw among the items of some sets — every item when none is named —, with the weights given for
     * some rarities, or the catalog's when none is given.
     *
     * @param list<string>       $sets
     * @param array<string, int> $weights
     */
    public function odds(array $sets = [], array $weights = []): Odds
    {
        $pool = [] === $sets ? $this->items : [];

        foreach ($sets as $set) {
            foreach ($this->itemsOf($set) as $item) {
                $pool[$item->code] = $item;
            }
        }

        if ([] === $weights) {
            $weights = array_map(static fn (Rarity $rarity): int => $rarity->weight, $this->rarities);
        }

        foreach (array_keys($weights) as $rarity) {
            $this->rarity($rarity);
        }

        return new Odds($pool, $weights);
    }

    /**
     * @template T of Rarity|Item|ItemSet|Pack
     *
     * @param list<T> $values
     *
     * @return array<string, T>
     */
    private function byCode(array $values, string $what): array
    {
        $byCode = [];

        foreach ($values as $value) {
            if (isset($byCode[$value->code])) {
                throw InvalidCollection::because(\sprintf('Two of the catalog\'s %s definitions are named "%s".', $what, $value->code));
            }

            $byCode[$value->code] = $value;
        }

        return $byCode;
    }
}
