<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

/**
 * What a holder has, read once for every plan made of it: the quantity of each item, the items carrying each tag, and
 * the items a craft may consume, all in the order of their codes.
 *
 * @internal
 */
final readonly class Pool
{
    /**
     * @param array<string, Holding>      $holdings   by item
     * @param array<string, int>          $quantities by item
     * @param array<string, list<string>> $tagged     the items carrying each tag
     * @param array<string, true>         $consumable the items a craft may consume
     */
    public function __construct(
        public array $holdings,
        public array $quantities,
        public array $tagged,
        public array $consumable,
    ) {
    }

    /**
     * @param array<string, Holding> $holdings by item, in the order of their codes
     */
    public static function of(array $holdings, RecipeBook $book): self
    {
        $quantities = [];
        $tagged = [];
        $consumable = [];

        foreach ($holdings as $item => $holding) {
            $quantities[$item] = $holding->quantity;

            foreach ($holding->tags as $tag) {
                $tagged[$tag][] = $item;
            }

            if ($book->mayConsume($item)) {
                $consumable[$item] = true;
            }
        }

        return new self($holdings, $quantities, $tagged, $consumable);
    }

    /**
     * The held items that may serve an ingredient, in the order of their codes; an item the policy protects never
     * serves a consumed ingredient.
     *
     * @return list<string>
     */
    public function candidates(Ingredient $ingredient): array
    {
        $items = [];

        if (null !== $ingredient->tag) {
            $items = $this->tagged[$ingredient->tag] ?? [];
        } elseif (null !== $ingredient->item && isset($this->quantities[$ingredient->item])) {
            $items = [$ingredient->item];
        }

        if (!$ingredient->consumed) {
            return $items;
        }

        return array_values(array_filter($items, fn (string $item): bool => isset($this->consumable[$item])));
    }
}
