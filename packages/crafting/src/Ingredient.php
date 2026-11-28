<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Tilttek\Crafting\Exception\InvalidRecipe;

/**
 * What a recipe asks for: one item by its code, or any items carrying a tag — `rare-metal` rather than one metal —, in
 * a quantity. A consumed ingredient is taken by the craft, its quantity once per repetition; a tool — a hammer, a
 * mortar — is only held, its quantity once for every repetition.
 */
final readonly class Ingredient
{
    /**
     * @throws InvalidRecipe
     */
    private function __construct(
        public ?string $item,
        public ?string $tag,
        public int $quantity,
        public bool $consumed,
    ) {
        Text::quantity($quantity, \sprintf('the ingredient %s', $this->label()), ItemQuantity::MAX_QUANTITY);
    }

    /**
     * @throws InvalidRecipe
     */
    public static function item(string $item, int $quantity = 1, bool $consumed = true): self
    {
        return new self(Text::code($item, 'an item'), null, $quantity, $consumed);
    }

    /**
     * @throws InvalidRecipe
     */
    public static function tagged(string $tag, int $quantity = 1, bool $consumed = true): self
    {
        return new self(null, Text::code($tag, 'a tag'), $quantity, $consumed);
    }

    /**
     * Whether a holding may serve this ingredient: it is the item asked for, or it carries the tag asked for.
     */
    public function accepts(Holding $holding): bool
    {
        return null === $this->tag ? $holding->item === $this->item : $holding->hasTag($this->tag);
    }

    /**
     * How many the craft needs, repeated `$times`: a consumed ingredient's quantity each time, a tool's once.
     */
    public function needed(int $times): int
    {
        return $this->consumed ? $this->quantity * $times : $this->quantity;
    }

    /**
     * The item's code, or `#` and the tag's code: `iron-ingot`, `#rare-metal`.
     */
    public function label(): string
    {
        return $this->item ?? '#'.$this->tag;
    }

    /**
     * @return array{item?: string, tag?: string, quantity: int, consumed: bool}
     */
    public function toArray(): array
    {
        return [
            ...(null === $this->item ? ['tag' => (string) $this->tag] : ['item' => $this->item]),
            'quantity' => $this->quantity,
            'consumed' => $this->consumed,
        ];
    }
}
