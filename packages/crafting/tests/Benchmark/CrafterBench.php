<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\MockClock;
use Tilttek\Crafting\Crafter;
use Tilttek\Crafting\Ingredient;
use Tilttek\Crafting\ItemQuantity;
use Tilttek\Crafting\Recipe;
use Tilttek\Crafting\RecipeBook;
use Tilttek\Crafting\Store\InMemoryInventory;

/**
 * The crafting page of the fullest book — 1,000 recipes of four ingredients, two of them asked by a tag — for a holder
 * of 500 items: what they can craft now, what they are closest to, one preview and one craft.
 */
final class CrafterBench
{
    private readonly Crafter $crafter;

    private readonly InMemoryInventory $inventory;

    private readonly ResourceReference $holder;

    private int $next = 0;

    public function __construct()
    {
        $tags = [];

        for ($i = 0; $i < 500; ++$i) {
            $tags['item-'.$i] = ['tag-'.($i % 50), 'family-'.($i % 7)];
        }

        $recipes = [];

        for ($r = 0; $r < RecipeBook::MAX_RECIPES; ++$r) {
            $recipes[] = new Recipe(
                'recipe-'.$r,
                [
                    Ingredient::item('item-'.($r % 500), 2),
                    Ingredient::item('item-'.(($r * 7 + 3) % 500)),
                    Ingredient::tagged('tag-'.($r % 50), 3),
                    Ingredient::tagged('family-'.($r % 7), 1, false),
                ],
                [new ItemQuantity('product-'.$r)],
            );
        }

        $this->inventory = new InMemoryInventory($tags);
        $this->crafter = new Crafter(new RecipeBook($recipes), $this->inventory, new MockClock('2026-11-27 10:00:00', 'UTC'));
        $this->holder = ResourceReference::of('member', '1');

        for ($i = 0; $i < 500; ++$i) {
            $this->inventory->put($this->holder, 'item-'.$i, 1 + $i % 4);
        }
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchCraftable(): int
    {
        return \count($this->crafter->craftable($this->holder));
    }

    /**
     * @Revs(10)
     *
     * @Iterations(3)
     */
    public function benchClosest(): int
    {
        return \count($this->crafter->closest($this->holder));
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchPreview(): bool
    {
        return $this->crafter->preview($this->holder, 'recipe-1')->isCraftable();
    }

    /**
     * @Revs(50)
     *
     * @Iterations(3)
     */
    public function benchCraft(): string
    {
        $this->inventory->put($this->holder, 'item-1', 2);
        $this->inventory->put($this->holder, 'item-10', 1);
        $this->inventory->put($this->holder, 'item-1', 3);

        return $this->crafter->craft($this->holder, 'recipe-1', 'bench#'.++$this->next)->key;
    }
}
