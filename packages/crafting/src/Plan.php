<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Phil\Resource\ResourceReference;

/**
 * What a craft would do with what a holder has now: each ingredient with the items set aside for it and what is still
 * missing, and the items it would consume, use, give back and produce. A plan moves nothing.
 */
final readonly class Plan
{
    /**
     * @param list<Requirement> $requirements one per ingredient, in the order of the recipe
     */
    public function __construct(
        public Recipe $recipe,
        public ResourceReference $holder,
        public int $times,
        public array $requirements,
    ) {
    }

    public function isCraftable(): bool
    {
        return [] === $this->missing();
    }

    /**
     * @return list<Requirement> the ingredients not fully held, in the order of the recipe
     */
    public function missing(): array
    {
        return array_values(array_filter($this->requirements, static fn (Requirement $requirement): bool => !$requirement->isMet()));
    }

    /**
     * How many items are missing in all, for every ingredient.
     */
    public function missingCount(): int
    {
        return array_sum(array_map(static fn (Requirement $requirement): int => $requirement->missing(), $this->requirements));
    }

    /**
     * How many of the items needed are held, for every ingredient.
     */
    public function heldCount(): int
    {
        return array_sum(array_map(static fn (Requirement $requirement): int => $requirement->held(), $this->requirements));
    }

    /**
     * @return list<ItemQuantity> the items the craft takes, in the order of their codes
     */
    public function consumed(): array
    {
        return $this->allocated(true);
    }

    /**
     * @return list<ItemQuantity> the tools the craft needs held and leaves, in the order of their codes
     */
    public function used(): array
    {
        return $this->allocated(false);
    }

    /**
     * @return list<ItemQuantity>
     */
    public function returned(): array
    {
        return $this->repeated($this->recipe->returns);
    }

    /**
     * @return list<ItemQuantity>
     */
    public function produced(): array
    {
        return $this->repeated($this->recipe->outputs);
    }

    /**
     * @return list<ItemQuantity>
     */
    private function allocated(bool $consumed): array
    {
        $quantities = [];

        foreach ($this->requirements as $requirement) {
            if ($requirement->ingredient->consumed !== $consumed) {
                continue;
            }

            foreach ($requirement->allocation as $item => $quantity) {
                $quantities[$item] = ($quantities[$item] ?? 0) + $quantity;
            }
        }

        return ItemQuantity::listOf($quantities);
    }

    /**
     * @param list<ItemQuantity> $items
     *
     * @return list<ItemQuantity>
     */
    private function repeated(array $items): array
    {
        $quantities = [];

        foreach ($items as $item) {
            $quantities[$item->item] = $item->quantity * $this->times;
        }

        return ItemQuantity::listOf($quantities);
    }
}
