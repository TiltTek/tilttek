<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Tilttek\Crafting\Exception\InvalidRecipe;

/**
 * A number of one item: what a recipe gives or gives back, and what a craft consumes, uses, gives back and produces.
 */
final readonly class ItemQuantity
{
    /** The most of one item a recipe names, before it is repeated. */
    public const int MAX_QUANTITY = 999;

    /** The most of one item a craft moves: the largest quantity, repeated the most times. */
    public const int MAX_MOVED = self::MAX_QUANTITY * Crafter::MAX_TIMES;

    /**
     * @throws InvalidRecipe
     */
    public function __construct(
        public string $item,
        public int $quantity = 1,
    ) {
        Text::code($item, 'an item');
        Text::quantity($quantity, \sprintf('"%s"', $item), self::MAX_MOVED);
    }

    /**
     * @param array<string, int> $quantities the quantity of each item
     *
     * @return list<self> the items in the order of their codes
     */
    public static function listOf(array $quantities): array
    {
        ksort($quantities, \SORT_STRING);
        $list = [];

        foreach ($quantities as $item => $quantity) {
            $list[] = new self($item, $quantity);
        }

        return $list;
    }

    /**
     * @return array{item: string, quantity: int}
     */
    public function toArray(): array
    {
        return ['item' => $this->item, 'quantity' => $this->quantity];
    }
}
