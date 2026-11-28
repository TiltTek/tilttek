<?php

declare(strict_types=1);

namespace Tilttek\Inventory;

use Phil\Resource\ResourceReference;

/**
 * The items of one inventory as a tree of places, to read where each one is, how deep, and what it weighs with what it
 * holds.
 *
 * @internal
 */
final readonly class Packing
{
    /** @var array<string, Item> */
    private array $items;

    /** @var array<string, list<Item>> by container key */
    private array $children;

    /**
     * @param iterable<Item> $items
     */
    public function __construct(iterable $items)
    {
        $byKey = [];
        $children = [];

        foreach ($items as $item) {
            $byKey[$item->key] = $item;
        }

        foreach ($byKey as $item) {
            if (null !== $item->place->container) {
                $children[$item->place->container][] = $item;
            }
        }

        $this->items = $byKey;
        $this->children = $children;
    }

    /**
     * The packing with the item written, replacing the item of the same key.
     */
    public function with(Item $item): self
    {
        return new self([...$this->items, $item->key => $item]);
    }

    public function without(string $key): self
    {
        $items = $this->items;
        unset($items[$key]);

        return new self($items);
    }

    public function item(string $key): ?Item
    {
        return $this->items[$key] ?? null;
    }

    /**
     * The items directly inside the container.
     *
     * @return list<Item>
     */
    public function children(string $container): array
    {
        return $this->children[$container] ?? [];
    }

    /**
     * Who carries the item, through the containers it is in, or null when it lies in the stash.
     */
    public function carrierOf(Place $place): ?ResourceReference
    {
        while (null !== $place->container) {
            $place = $this->items[$place->container]->place;
        }

        return $place->carrier;
    }

    /**
     * How many containers an item put there would be in.
     */
    public function depthOf(Place $place): int
    {
        $depth = 0;

        while (null !== $place->container) {
            ++$depth;
            $place = $this->items[$place->container]->place;
        }

        return $depth;
    }

    /**
     * How many containers nest below the item: 0 for an item that holds nothing.
     */
    public function height(Item $item): int
    {
        $height = 0;

        foreach ($this->children($item->key) as $child) {
            $height = max($height, 1 + $this->height($child));
        }

        return $height;
    }

    /**
     * Whether the place is inside the item, or the item itself.
     */
    public function isWithin(Place $place, Item $item): bool
    {
        while (null !== $place->container) {
            if ($place->container === $item->key) {
                return true;
            }

            $place = $this->items[$place->container]->place;
        }

        return false;
    }

    /**
     * What the item weighs on whoever carries it, or the slots it takes, with what it holds unless its contents weigh
     * nothing.
     */
    private function burdenOf(Item $item, Measure $measure): int
    {
        $total = $item->own($measure);

        if (true === $item->container?->countsContents) {
            $total += $this->held($item->key, $measure);
        }

        return $total;
    }

    /**
     * What the container holds against its bounds.
     */
    public function fill(Item $container): Fill
    {
        return new Fill(
            $container->key,
            $this->held($container->key, Measure::Weight),
            $this->held($container->key, Measure::Slots),
            $container->container?->maxWeight,
            $container->container?->maxSlots,
        );
    }

    /**
     * The containers from the place up to the outermost, the nearest first.
     *
     * @return list<Item>
     */
    public function chain(Place $place): array
    {
        $chain = [];

        while (null !== $place->container) {
            $container = $this->items[$place->container];
            $chain[] = $container;
            $place = $container->place;
        }

        return $chain;
    }

    /**
     * What the carrier carries, by the measure.
     */
    public function carried(ResourceReference $carrier, Measure $measure): int
    {
        $total = 0;

        foreach ($this->items as $item) {
            if ($item->place->carrier instanceof ResourceReference && $item->place->carrier->equals($carrier)) {
                $total += $this->burdenOf($item, $measure);
            }
        }

        return $total;
    }

    /**
     * The carriers holding an item directly, in the order of their references.
     *
     * @return list<ResourceReference>
     */
    public function carriers(): array
    {
        $carriers = [];

        foreach ($this->items as $item) {
            if ($item->place->carrier instanceof ResourceReference) {
                $carriers[$item->place->carrier->toString()] = $item->place->carrier;
            }
        }

        ksort($carriers, \SORT_STRING);

        return array_values($carriers);
    }

    private function held(string $container, Measure $measure): int
    {
        $total = 0;

        foreach ($this->children($container) as $child) {
            $total += $this->burdenOf($child, $measure);
        }

        return $total;
    }
}
