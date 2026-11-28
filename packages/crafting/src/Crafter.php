<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Crafting\Contract\CraftObserver;
use Tilttek\Crafting\Contract\Inventory;
use Tilttek\Crafting\Exception\CraftRefused;
use Tilttek\Crafting\Exception\InvalidRecipe;
use Tilttek\Crafting\Exception\RecipeNotFound;

/**
 * Crafts from the recipe book with what holders have: shows what a recipe would take and what is still missing, lists
 * what a holder can craft now and what they are closest to, and crafts once per key, at once or not at all.
 *
 * Items are set aside ingredient by ingredient: those named by their code first, then those asked by a tag that the
 * member chose, then the other tags, the tag fewest held items carry first, each from its items in the order of their
 * codes. A tool is held, never consumed, and an item set aside for one ingredient serves no other.
 */
final readonly class Crafter
{
    /** The most times one craft repeats a recipe. */
    public const int MAX_TIMES = 100;

    /** The most holdings the crafter reads of one holder. */
    public const int MAX_HOLDINGS = 10_000;

    /** The most plans `closest()` returns. */
    public const int MAX_CLOSEST = 50;

    /**
     * @param list<CraftObserver> $observers
     */
    public function __construct(
        public RecipeBook $book,
        private Inventory $inventory,
        private ClockInterface $clock,
        private array $observers = [],
    ) {
    }

    /**
     * What crafting a recipe `$times` would take of what the holder has now, and what is missing.
     *
     * @throws RecipeNotFound
     * @throws InvalidRecipe  when the repetitions or the selection are out of bounds
     * @throws CraftRefused   when the inventory gives more holdings than the crafter reads
     */
    public function preview(ResourceReference $holder, string $recipe, int $times = 1, ?Selection $selection = null): Plan
    {
        Text::quantity($times, 'repetitions of a craft', self::MAX_TIMES);

        return $this->plan($this->book->recipe($recipe), $holder, $times, $this->pool($holder), $selection);
    }

    /**
     * Crafts a recipe `$times` under a key: the same key given again returns the craft it made, without crafting
     * again. The inventory applies the craft at once or not at all.
     *
     * @throws RecipeNotFound
     * @throws InvalidRecipe  when the key, the repetitions or the selection are out of bounds
     * @throws CraftRefused   when items are missing, or the key made another craft
     */
    public function craft(ResourceReference $holder, string $recipe, string $key, int $times = 1, ?Selection $selection = null): Craft
    {
        Text::key($key);
        Text::quantity($times, 'repetitions of a craft', self::MAX_TIMES);
        $definition = $this->book->recipe($recipe);
        $earlier = $this->inventory->find($key);

        if ($earlier instanceof Craft) {
            return $this->same($earlier, $holder, $recipe, $times);
        }

        $plan = $this->plan($definition, $holder, $times, $this->pool($holder), $selection);

        if (!$plan->isCraftable()) {
            throw CraftRefused::because(\sprintf('The recipe "%s" cannot be crafted: %s missing.', $recipe, implode(', ', array_map(static fn (Requirement $requirement): string => \sprintf('%d × %s', $requirement->missing(), $requirement->ingredient->label()), $plan->missing()))));
        }

        $craft = new Craft($key, $holder, $recipe, $times, $plan->consumed(), $plan->used(), $plan->returned(), $plan->produced(), $this->clock->now(), $definition->evolves());

        if (!$this->inventory->apply($craft)) {
            $earlier = $this->inventory->find($key) ?? throw CraftRefused::because(\sprintf('The key "%s" is recorded, yet its craft cannot be read.', $key));

            return $this->same($earlier, $holder, $recipe, $times);
        }

        $event = new Crafted($holder, $recipe, $key, $craft->at);

        foreach ($this->observers as $observer) {
            $observer->crafted($event);
        }

        return $craft;
    }

    /**
     * The recipes the holder can craft once now, in the order of the book.
     *
     * @return list<Plan>
     *
     * @throws CraftRefused when the inventory gives more holdings than the crafter reads
     */
    public function craftable(ResourceReference $holder): array
    {
        $pool = $this->pool($holder);
        $plans = [];

        foreach ($this->book->recipes() as $recipe) {
            if (!$this->mayBeCraftable($recipe, $pool)) {
                continue;
            }

            $plan = $this->plan($recipe, $holder, 1, $pool, null);

            if ($plan->isCraftable()) {
                $plans[] = $plan;
            }
        }

        return $plans;
    }

    /**
     * The recipes the holder cannot craft yet but holds something for, the fewest items missing first, then in the
     * order of the book: what to look for next.
     *
     * @return list<Plan>
     *
     * @throws InvalidRecipe when the limit is out of bounds
     * @throws CraftRefused  when the inventory gives more holdings than the crafter reads
     */
    public function closest(ResourceReference $holder, int $limit = 10): array
    {
        if ($limit < 1 || $limit > self::MAX_CLOSEST) {
            throw InvalidRecipe::because(\sprintf('The closest recipes are 1 to %d; %d is out of bounds.', self::MAX_CLOSEST, $limit));
        }

        $pool = $this->pool($holder);
        $plans = [];

        foreach ($this->book->recipes() as $recipe) {
            $plan = $this->plan($recipe, $holder, 1, $pool, null);

            if (!$plan->isCraftable() && $plan->heldCount() > 0) {
                $plans[] = $plan;
            }
        }

        // usort() keeps the order of equal elements: the book's order breaks ties.
        usort($plans, static fn (Plan $a, Plan $b): int => $a->missingCount() <=> $b->missingCount());

        return \array_slice($plans, 0, $limit);
    }

    /**
     * Whether every ingredient named by its code is held in its quantity: a recipe that fails this is not craftable, and
     * needs no plan to tell.
     */
    private function mayBeCraftable(Recipe $recipe, Pool $pool): bool
    {
        return array_all($recipe->ingredients, static fn (Ingredient $ingredient): bool => null === $ingredient->item || !(($pool->quantities[$ingredient->item] ?? 0) < $ingredient->quantity));
    }

    /**
     * @return Craft the earlier craft, when it is the one asked
     *
     * @throws CraftRefused
     */
    private function same(Craft $earlier, ResourceReference $holder, string $recipe, int $times): Craft
    {
        if (!$earlier->isFor($holder, $recipe, $times)) {
            throw CraftRefused::because(\sprintf('The key "%s" already made another craft.', $earlier->key));
        }

        return $earlier;
    }

    /**
     * The holder's items, read once for every plan made of them.
     *
     * @throws CraftRefused
     */
    private function pool(ResourceReference $holder): Pool
    {
        $pool = [];
        $count = 0;

        foreach ($this->inventory->holdings($holder) as $holding) {
            if (++$count > self::MAX_HOLDINGS) {
                throw CraftRefused::because(\sprintf('The crafter reads at most %d holdings of a holder.', self::MAX_HOLDINGS));
            }

            if (isset($pool[$holding->item])) {
                throw CraftRefused::because(\sprintf('The inventory gives one holding per item; "%s" came twice.', $holding->item));
            }

            $pool[$holding->item] = $holding;
        }

        ksort($pool, \SORT_STRING);

        return Pool::of($pool, $this->book);
    }

    /**
     * @throws InvalidRecipe
     */
    private function plan(Recipe $recipe, ResourceReference $holder, int $times, Pool $pool, ?Selection $selection): Plan
    {
        // What this plan set aside so far, by item: the pool is shared by every plan and never copied.
        $taken = [];
        $allocations = [];

        foreach ($this->order($recipe, $pool, $selection) as $position) {
            $ingredient = $recipe->ingredients[$position];
            $needed = $ingredient->needed($times);
            $chosen = $selection?->for($position);
            $allocation = [];

            if (null !== $chosen) {
                $this->checkChoice($recipe, $position, $chosen, $needed, $pool);
            }

            $wanted = $chosen ?? array_fill_keys($pool->candidates($ingredient), $needed);
            $left = $needed;

            foreach ($wanted as $item => $quantity) {
                $take = min($quantity, $left, ($pool->quantities[$item] ?? 0) - ($taken[$item] ?? 0));

                if ($take > 0) {
                    $allocation[$item] = $take;
                    $taken[$item] = ($taken[$item] ?? 0) + $take;
                    $left -= $take;
                }
            }

            $allocations[$position] = $allocation;
        }

        $requirements = [];

        foreach ($recipe->ingredients as $position => $ingredient) {
            $requirements[] = new Requirement($ingredient, $ingredient->needed($times), $allocations[$position]);
        }

        return new Plan($recipe, $holder, $times, $requirements);
    }

    /**
     * The positions of the ingredients in the order they are served: by code, then the tags chosen, then the other
     * tags, the one fewest held items carry first.
     *
     * @return list<int>
     *
     * @throws InvalidRecipe
     */
    private function order(Recipe $recipe, Pool $pool, ?Selection $selection): array
    {
        $byCode = [];
        $chosen = [];
        $tagged = [];

        foreach (array_keys($selection->choices ?? []) as $position) {
            if (null === ($recipe->ingredients[$position] ?? null)?->tag) {
                throw InvalidRecipe::because(\sprintf('A selection chooses items for an ingredient asked by a tag; the ingredient %d of the recipe "%s" is not one.', $position, $recipe->code));
            }
        }

        foreach ($recipe->ingredients as $position => $ingredient) {
            match (true) {
                null !== $ingredient->item => $byCode[] = $position,
                null !== $selection?->for($position) => $chosen[] = $position,
                default => $tagged[$position] = \count($pool->candidates($ingredient)),
            };
        }

        // asort() keeps the order of equal counts: the recipe's order breaks ties.
        asort($tagged);

        return [...$byCode, ...$chosen, ...array_keys($tagged)];
    }

    /**
     * @param array<string, int> $chosen
     *
     * @throws InvalidRecipe
     */
    private function checkChoice(Recipe $recipe, int $position, array $chosen, int $needed, Pool $pool): void
    {
        $ingredient = $recipe->ingredients[$position];

        if (array_sum($chosen) !== $needed) {
            throw InvalidRecipe::because(\sprintf('The items chosen for the ingredient %s of the recipe "%s" add up to %d; it needs %d.', $ingredient->label(), $recipe->code, array_sum($chosen), $needed));
        }

        foreach (array_keys($chosen) as $item) {
            $holding = $pool->holdings[$item] ?? null;

            if (($ingredient->consumed && !$this->book->mayConsume($item)) || (null !== $holding && !$ingredient->accepts($holding))) {
                throw InvalidRecipe::because(\sprintf('"%s" cannot serve the ingredient %s of the recipe "%s".', $item, $ingredient->label(), $recipe->code));
            }
        }
    }
}
