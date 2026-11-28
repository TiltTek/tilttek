<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A craft made, or about to be applied to an inventory: its key, which makes it count once, the holder, the recipe,
 * how many times it was repeated, and the items it consumes, needs held, gives back and produces. An evolving craft
 * names the item that evolved, so that an inventory may keep it as the same object under its new item.
 */
final readonly class Craft
{
    /**
     * @param list<ItemQuantity> $consumed
     * @param list<ItemQuantity> $used
     * @param list<ItemQuantity> $returned
     * @param list<ItemQuantity> $produced
     */
    public function __construct(
        public string $key,
        public ResourceReference $holder,
        public string $recipe,
        public int $times,
        public array $consumed,
        public array $used,
        public array $returned,
        public array $produced,
        public DateTimeImmutable $at,
        public ?string $evolved = null,
    ) {
        Text::key($key);
        Text::code($recipe, 'a recipe');
        Text::quantity($times, 'repetitions of a craft', Crafter::MAX_TIMES);

        if (null !== $evolved) {
            Text::code($evolved, 'an item');
        }
    }

    /**
     * Whether this craft is the one asked: for this holder, of this recipe, repeated as many times.
     */
    public function isFor(ResourceReference $holder, string $recipe, int $times): bool
    {
        return $this->holder->equals($holder) && $this->recipe === $recipe && $this->times === $times;
    }

    /**
     * @return array{key: string, holder: string, recipe: string, times: int, consumed: list<array{item: string, quantity: int}>, used: list<array{item: string, quantity: int}>, returned: list<array{item: string, quantity: int}>, produced: list<array{item: string, quantity: int}>, at: string, evolved: string|null}
     */
    public function toArray(): array
    {
        $list = static fn (array $items): array => array_map(static fn (ItemQuantity $item): array => $item->toArray(), $items);

        return [
            'key' => $this->key,
            'holder' => $this->holder->toString(),
            'recipe' => $this->recipe,
            'times' => $this->times,
            'consumed' => $list($this->consumed),
            'used' => $list($this->used),
            'returned' => $list($this->returned),
            'produced' => $list($this->produced),
            'at' => $this->at->format(DateTimeImmutable::ATOM),
            'evolved' => $this->evolved,
        ];
    }
}
