<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Tilttek\Crafting\Exception\InvalidRecipe;

/**
 * A recipe kept as data: the ingredients it takes or only needs, the items it produces, the items it gives back — the
 * empty flask of a potion —, and attributes the application reads — a duration, a workshop. An evolving recipe takes
 * one item, its first ingredient, and turns it into one item of another kind.
 *
 * A recipe is deterministic: it always produces what it declares. A random result belongs to a loot table whose odds
 * are published, drawn after the craft.
 */
final readonly class Recipe
{
    public const int MAX_INGREDIENTS = 16;

    public const int MAX_OUTPUTS = 8;

    public const int MAX_RETURNS = 8;

    public const int MAX_ATTRIBUTES = 10;

    /** The most characters of an attribute's text. */
    public const int MAX_ATTRIBUTE_LENGTH = 100;

    /**
     * @param list<Ingredient>               $ingredients
     * @param list<ItemQuantity>             $outputs
     * @param list<ItemQuantity>             $returns
     * @param array<string, string|int|bool> $attributes
     *
     * @throws InvalidRecipe
     */
    public function __construct(
        public string $code,
        public array $ingredients,
        public array $outputs,
        public array $returns = [],
        public RecipeKind $kind = RecipeKind::Combine,
        public array $attributes = [],
    ) {
        Text::code($code, 'a recipe');
        $this->checkIngredients();
        $this->checkItems($this->outputs, 'outputs', 1, self::MAX_OUTPUTS);
        $this->checkItems($this->returns, 'returns', 0, self::MAX_RETURNS);
        $this->checkAttributes();

        if (RecipeKind::Evolve === $kind) {
            $this->checkEvolution();
        }
    }

    /**
     * The item an evolving recipe turns into its next form; none for a recipe that combines.
     */
    public function evolves(): ?string
    {
        return RecipeKind::Evolve === $this->kind ? $this->ingredients[0]->item : null;
    }

    /**
     * Whether the recipe consumes this item, named as an ingredient.
     */
    public function consumes(string $item): bool
    {
        return array_any($this->ingredients, static fn (Ingredient $ingredient): bool => $ingredient->consumed && $ingredient->item === $item);
    }

    /**
     * Whether the recipe produces this item.
     */
    public function produces(string $item): bool
    {
        return array_any($this->outputs, static fn (ItemQuantity $output): bool => $output->item === $item);
    }

    private function checkIngredients(): void
    {
        $count = \count($this->ingredients);

        if ($count < 1 || $count > self::MAX_INGREDIENTS) {
            throw InvalidRecipe::because(\sprintf('The recipe "%s" lists 1 to %d ingredients; it has %d.', $this->code, self::MAX_INGREDIENTS, $count));
        }

        $labels = [];
        $consumes = false;

        foreach ($this->ingredients as $ingredient) {
            // The same item or tag may be both taken and held as a tool, never twice either way.
            $label = $ingredient->label().($ingredient->consumed ? '' : ' as a tool');

            if (isset($labels[$label])) {
                throw InvalidRecipe::because(\sprintf('The recipe "%s" names the ingredient %s twice.', $this->code, $label));
            }

            $labels[$label] = true;
            $consumes = $consumes || $ingredient->consumed;
        }

        if (!$consumes) {
            throw InvalidRecipe::because(\sprintf('The recipe "%s" consumes at least one ingredient; tools alone produce nothing.', $this->code));
        }
    }

    /**
     * @param list<ItemQuantity> $items
     */
    private function checkItems(array $items, string $what, int $min, int $max): void
    {
        $count = \count($items);

        if ($count < $min || $count > $max) {
            throw InvalidRecipe::because(\sprintf('The recipe "%s" lists %d to %d %s; it has %d.', $this->code, $min, $max, $what, $count));
        }

        $seen = [];

        foreach ($items as $item) {
            if ($item->quantity > ItemQuantity::MAX_QUANTITY) {
                throw InvalidRecipe::because(\sprintf('The quantity of "%s" in the recipe "%s" is 1 to %d; it is %d.', $item->item, $this->code, ItemQuantity::MAX_QUANTITY, $item->quantity));
            }

            if (isset($seen[$item->item])) {
                throw InvalidRecipe::because(\sprintf('The recipe "%s" names "%s" twice among its %s.', $this->code, $item->item, $what));
            }

            $seen[$item->item] = true;
        }
    }

    private function checkAttributes(): void
    {
        if (\count($this->attributes) > self::MAX_ATTRIBUTES) {
            throw InvalidRecipe::because(\sprintf('A recipe carries at most %d attributes; "%s" has %d.', self::MAX_ATTRIBUTES, $this->code, \count($this->attributes)));
        }

        foreach ($this->attributes as $name => $value) {
            Text::code($name, 'an attribute');

            if (\is_string($value) && (1 !== preg_match('/^\P{Cc}{1,'.self::MAX_ATTRIBUTE_LENGTH.'}\z/u', $value) || trim($value) !== $value)) {
                throw InvalidRecipe::because(\sprintf('The attribute "%s" is 1 to %d characters of UTF-8 without control characters nor surrounding spaces.', $name, self::MAX_ATTRIBUTE_LENGTH));
            }
        }
    }

    private function checkEvolution(): void
    {
        $base = $this->ingredients[0];

        if (null === $base->item || !$base->consumed || 1 !== $base->quantity) {
            throw InvalidRecipe::because(\sprintf('The evolving recipe "%s" takes its first ingredient, one item named by its code, and consumes it.', $this->code));
        }

        if (1 !== \count($this->outputs) || 1 !== $this->outputs[0]->quantity || $this->outputs[0]->item === $base->item) {
            throw InvalidRecipe::because(\sprintf('The evolving recipe "%s" produces one item of another kind than "%s".', $this->code, $base->item));
        }
    }
}
