<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Tilttek\Crafting\Exception\InvalidRecipe;

/**
 * The items a member chooses for the ingredients asked by a tag — which metal goes into the blade —, by the position of
 * the ingredient in its recipe. An ingredient left out is served by the library, from the items carrying its tag in the
 * order of their codes.
 */
final readonly class Selection
{
    /** The most items chosen for one ingredient. */
    public const int MAX_ITEMS = 16;

    /** @var array<int, array<string, int>> the quantity of each item, by the position of the ingredient */
    public array $choices;

    /**
     * @param array<int, array<string, int>> $choices
     *
     * @throws InvalidRecipe
     */
    public function __construct(array $choices)
    {
        $checked = [];

        foreach ($choices as $position => $items) {
            if ($position < 0 || $position >= Recipe::MAX_INGREDIENTS) {
                throw InvalidRecipe::because(\sprintf('A selection names the ingredients by their position, 0 to %d; %d is not one.', Recipe::MAX_INGREDIENTS - 1, $position));
            }

            if ([] === $items || \count($items) > self::MAX_ITEMS) {
                throw InvalidRecipe::because(\sprintf('A selection chooses 1 to %d items for an ingredient; %d were chosen for the ingredient %d.', self::MAX_ITEMS, \count($items), $position));
            }

            foreach ($items as $item => $quantity) {
                Text::code($item, 'an item');
                $checked[$position][$item] = Text::quantity($quantity, \sprintf('"%s" in the selection', $item), ItemQuantity::MAX_MOVED);
            }

            ksort($checked[$position], \SORT_STRING);
        }

        ksort($checked);
        $this->choices = $checked;
    }

    /**
     * @return array<string, int>|null the items chosen for the ingredient at this position, if any
     */
    public function for(int $position): ?array
    {
        return $this->choices[$position] ?? null;
    }
}
