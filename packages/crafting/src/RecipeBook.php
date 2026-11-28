<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Tilttek\Crafting\Contract\ConsumptionPolicy;
use Tilttek\Crafting\Exception\InvalidRecipe;
use Tilttek\Crafting\Exception\RecipeNotFound;
use ValueError;

/**
 * The recipes of a site, by their codes, read from plain values — a configuration file, a database row — and written
 * back to them. The book refuses a recipe that consumes an item its consumption policy protects.
 */
final readonly class RecipeBook
{
    /** The most recipes a book holds. */
    public const int MAX_RECIPES = 1000;

    /** @var array<string, Recipe> */
    private array $recipes;

    /**
     * @param list<Recipe> $recipes
     *
     * @throws InvalidRecipe
     */
    public function __construct(
        array $recipes,
        public ?ConsumptionPolicy $policy = null,
    ) {
        if (\count($recipes) > self::MAX_RECIPES) {
            throw InvalidRecipe::because(\sprintf('A recipe book holds at most %d recipes; this one has %d.', self::MAX_RECIPES, \count($recipes)));
        }

        $byCode = [];

        foreach ($recipes as $recipe) {
            if (isset($byCode[$recipe->code])) {
                throw InvalidRecipe::because(\sprintf('The recipe book holds the recipe "%s" twice.', $recipe->code));
            }

            foreach ($recipe->ingredients as $ingredient) {
                if ($ingredient->consumed && null !== $ingredient->item && !$this->mayConsume($ingredient->item)) {
                    throw InvalidRecipe::because(\sprintf('The recipe "%s" consumes "%s", which no craft may consume.', $recipe->code, $ingredient->item));
                }
            }

            $byCode[$recipe->code] = $recipe;
        }

        $this->recipes = $byCode;
    }

    /**
     * Reads a book from plain values:
     *
     * ```php
     * ['recipes' => [
     *     'healing-potion' => [
     *         'kind' => 'combine',                                  // or 'evolve'; 'combine' when left out
     *         'ingredients' => [
     *             ['item' => 'red-herb', 'quantity' => 2],
     *             ['tag' => 'clear-water'],                         // quantity 1 when left out
     *             ['item' => 'mortar', 'consumed' => false],        // a tool, held and never taken
     *         ],
     *         'outputs' => [['item' => 'healing-potion']],
     *         'returns' => [['item' => 'empty-bucket']],            // none when left out
     *         'attributes' => ['minutes' => 10],                    // none when left out
     *     ],
     * ]]
     * ```
     *
     * @param array<mixed> $definition
     *
     * @throws InvalidRecipe
     */
    public static function fromArray(array $definition, ?ConsumptionPolicy $policy = null): self
    {
        self::keys($definition, ['recipes'], 'A recipe book');
        $recipes = $definition['recipes'] ?? [];

        if (!\is_array($recipes)) {
            throw InvalidRecipe::because('The recipes of a book are a map of codes to recipes.');
        }

        if (\count($recipes) > self::MAX_RECIPES) {
            throw InvalidRecipe::because(\sprintf('A recipe book holds at most %d recipes; this one has %d.', self::MAX_RECIPES, \count($recipes)));
        }

        $list = [];

        foreach ($recipes as $code => $recipe) {
            if (!\is_string($code) || !\is_array($recipe)) {
                throw InvalidRecipe::because('The recipes of a book are a map of codes to recipes.');
            }

            $list[] = self::readRecipe($code, $recipe);
        }

        return new self($list, $policy);
    }

    /**
     * Writes the book back to the plain values `fromArray()` reads, every value spelled out.
     *
     * @return array{recipes: array<string, array{kind: string, ingredients: list<array{item?: string, tag?: string, quantity: int, consumed: bool}>, outputs: list<array{item: string, quantity: int}>, returns: list<array{item: string, quantity: int}>, attributes: array<string, string|int|bool>}>}
     */
    public function toArray(): array
    {
        $recipes = [];

        foreach ($this->recipes as $code => $recipe) {
            $recipes[$code] = [
                'kind' => $recipe->kind->value,
                'ingredients' => array_map(static fn (Ingredient $ingredient): array => $ingredient->toArray(), $recipe->ingredients),
                'outputs' => array_map(static fn (ItemQuantity $output): array => $output->toArray(), $recipe->outputs),
                'returns' => array_map(static fn (ItemQuantity $return): array => $return->toArray(), $recipe->returns),
                'attributes' => $recipe->attributes,
            ];
        }

        return ['recipes' => $recipes];
    }

    /**
     * @throws RecipeNotFound
     */
    public function recipe(string $code): Recipe
    {
        return $this->recipes[$code] ?? throw RecipeNotFound::named($code);
    }

    public function has(string $code): bool
    {
        return isset($this->recipes[$code]);
    }

    /**
     * @return list<Recipe> the recipes in the order the book was given them
     */
    public function recipes(): array
    {
        return array_values($this->recipes);
    }

    public function count(): int
    {
        return \count($this->recipes);
    }

    /**
     * The recipes that consume an item named by its code — what a member can make of what they hold.
     *
     * @return list<Recipe>
     */
    public function consuming(string $item): array
    {
        return array_values(array_filter($this->recipes, static fn (Recipe $recipe): bool => $recipe->consumes($item)));
    }

    /**
     * The recipes that produce an item — how a member can get it.
     *
     * @return list<Recipe>
     */
    public function producing(string $item): array
    {
        return array_values(array_filter($this->recipes, static fn (Recipe $recipe): bool => $recipe->produces($item)));
    }

    /**
     * Whether a craft may consume an item: always, without a consumption policy.
     */
    public function mayConsume(string $item): bool
    {
        return !$this->policy instanceof ConsumptionPolicy || $this->policy->mayConsume($item);
    }

    /**
     * @param array<mixed> $recipe
     */
    private static function readRecipe(string $code, array $recipe): Recipe
    {
        self::keys($recipe, ['kind', 'ingredients', 'outputs', 'returns', 'attributes'], \sprintf('The recipe "%s"', $code));

        $kind = $recipe['kind'] ?? RecipeKind::Combine->value;

        try {
            $kind = RecipeKind::from(\is_string($kind) ? $kind : '');
        } catch (ValueError) {
            throw InvalidRecipe::because(\sprintf('The kind of the recipe "%s" is "combine" or "evolve".', $code));
        }

        $ingredients = [];

        foreach (self::entries($recipe['ingredients'] ?? null, $code, 'ingredients', Recipe::MAX_INGREDIENTS) as $entry) {
            self::keys($entry, ['item', 'tag', 'quantity', 'consumed'], \sprintf('An ingredient of the recipe "%s"', $code));
            $quantity = self::quantity($entry, $code);
            $consumed = $entry['consumed'] ?? true;

            if (!\is_bool($consumed)) {
                throw InvalidRecipe::because(\sprintf('Whether an ingredient of the recipe "%s" is consumed is true or false.', $code));
            }

            $item = $entry['item'] ?? null;
            $tag = $entry['tag'] ?? null;
            $ingredients[] = match (true) {
                \is_string($item) && null === $tag => Ingredient::item($item, $quantity, $consumed),
                \is_string($tag) && null === $item => Ingredient::tagged($tag, $quantity, $consumed),
                default => throw InvalidRecipe::because(\sprintf('An ingredient of the recipe "%s" names either an item or a tag.', $code)),
            };
        }

        $attributes = $recipe['attributes'] ?? [];

        if (!\is_array($attributes) || \count($attributes) > Recipe::MAX_ATTRIBUTES) {
            throw InvalidRecipe::because(\sprintf('The attributes of the recipe "%s" are a map of at most %d names to texts, integers or booleans.', $code, Recipe::MAX_ATTRIBUTES));
        }

        $checked = [];

        foreach ($attributes as $name => $value) {
            if (!\is_string($name) || (!\is_string($value) && !\is_int($value) && !\is_bool($value))) {
                throw InvalidRecipe::because(\sprintf('The attributes of the recipe "%s" are a map of at most %d names to texts, integers or booleans.', $code, Recipe::MAX_ATTRIBUTES));
            }

            $checked[$name] = $value;
        }

        return new Recipe(
            $code,
            $ingredients,
            self::items($recipe['outputs'] ?? null, $code, 'outputs', Recipe::MAX_OUTPUTS),
            self::items($recipe['returns'] ?? [], $code, 'returns', Recipe::MAX_RETURNS),
            $kind,
            $checked,
        );
    }

    /**
     * @return list<ItemQuantity>
     */
    private static function items(mixed $entries, string $code, string $what, int $max): array
    {
        $items = [];

        foreach (self::entries($entries, $code, $what, $max) as $entry) {
            self::keys($entry, ['item', 'quantity'], \sprintf('An entry of the %s of the recipe "%s"', $what, $code));
            $item = $entry['item'] ?? null;

            if (!\is_string($item)) {
                throw InvalidRecipe::because(\sprintf('An entry of the %s of the recipe "%s" names an item.', $what, $code));
            }

            $items[] = new ItemQuantity($item, self::quantity($entry, $code));
        }

        return $items;
    }

    /**
     * @return list<array<mixed>>
     */
    private static function entries(mixed $entries, string $code, string $what, int $max): array
    {
        if (!\is_array($entries) || !array_is_list($entries) || \count($entries) > $max) {
            throw InvalidRecipe::because(\sprintf('The %s of the recipe "%s" are a list of at most %d entries.', $what, $code, $max));
        }

        foreach ($entries as $entry) {
            if (!\is_array($entry)) {
                throw InvalidRecipe::because(\sprintf('The %s of the recipe "%s" are a list of at most %d entries.', $what, $code, $max));
            }
        }

        return $entries;
    }

    /**
     * @param array<mixed> $entry
     */
    private static function quantity(array $entry, string $code): int
    {
        $quantity = $entry['quantity'] ?? 1;

        if (!\is_int($quantity)) {
            throw InvalidRecipe::because(\sprintf('A quantity of the recipe "%s" is an integer.', $code));
        }

        return $quantity;
    }

    /**
     * @param array<mixed> $values
     * @param list<string> $known
     */
    private static function keys(array $values, array $known, string $what): void
    {
        foreach (array_keys($values) as $key) {
            if (!\in_array($key, $known, true)) {
                throw InvalidRecipe::because(\sprintf('%s has no "%s"; it knows %s.', $what, $key, implode(', ', $known)));
            }
        }
    }
}
