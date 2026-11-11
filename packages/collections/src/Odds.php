<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Random\Randomizer;
use Tilttek\Collections\Exception\InvalidCollection;

/**
 * The published odds of a draw, and the draw itself: a rarity by weight, among those that have an item in the pool,
 * then an item of that rarity, every item of it as likely as another. The odds of a rarity are its weight over the
 * weights of the rarities drawn; the odds of an item are its rarity's odds shared among the items of that rarity.
 */
final readonly class Odds
{
    /**
     * The weights of the rarities drawn, those with an item in the pool and a weight, in the order they were given.
     *
     * @var array<string, int>
     */
    public array $weights;

    /**
     * The items of the pool by rarity, in the pool's order.
     *
     * @var array<string, non-empty-list<Item>>
     */
    public array $items;

    /**
     * @param array<Item>        $pool
     * @param array<string, int> $weights
     */
    public function __construct(array $pool, array $weights)
    {
        $items = [];

        foreach ($pool as $item) {
            $items[$item->rarity][] = $item;
        }

        $this->weights = array_intersect_key($weights, $items);

        if ([] === $this->weights) {
            throw InvalidCollection::because('A draw has at least one item of a rarity it gives a weight to.');
        }

        $this->items = array_intersect_key($items, $this->weights);
    }

    /**
     * The chance, from 0 to 1, that a draw lands on each rarity.
     *
     * @return array<string, float>
     */
    public function rarities(): array
    {
        $total = array_sum($this->weights);

        return array_map(static fn (int $weight): float => $weight / $total, $this->weights);
    }

    /**
     * The chance, from 0 to 1, that a draw lands on each item.
     *
     * @return array<string, float>
     */
    public function itemOdds(): array
    {
        $odds = [];

        foreach ($this->rarities() as $rarity => $chance) {
            foreach ($this->items[$rarity] as $item) {
                $odds[$item->code] = $chance / \count($this->items[$rarity]);
            }
        }

        return $odds;
    }

    public function draw(Randomizer $randomizer): Item
    {
        $roll = $randomizer->getInt(1, array_sum($this->weights));

        foreach ($this->weights as $rarity => $weight) {
            $roll -= $weight;

            if ($roll <= 0) {
                $items = $this->items[$rarity];

                return $items[$randomizer->getInt(0, \count($items) - 1)];
            }
        }

        throw InvalidCollection::because('The draw fell outside its weights.');
    }
}
