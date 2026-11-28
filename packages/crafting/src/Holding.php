<?php

declare(strict_types=1);

namespace Tilttek\Crafting;

use Tilttek\Crafting\Exception\InvalidRecipe;

/**
 * What a holder has of one item, as the inventory reads it: the item, how many, and the tags a recipe may ask for
 * instead of the item itself — `rare-metal`, `herb`.
 */
final readonly class Holding
{
    /** The most of one item a holding counts. */
    public const int MAX_QUANTITY = 1_000_000_000;

    /** The most tags an item carries. */
    public const int MAX_TAGS = 16;

    /** @var list<string> the tags, sorted, each once */
    public array $tags;

    /**
     * @param list<string> $tags
     *
     * @throws InvalidRecipe
     */
    public function __construct(
        public string $item,
        public int $quantity,
        array $tags = [],
    ) {
        Text::code($item, 'an item');
        Text::quantity($quantity, \sprintf('the holding of "%s"', $item), self::MAX_QUANTITY);

        if (\count($tags) > self::MAX_TAGS) {
            throw InvalidRecipe::because(\sprintf('An item carries at most %d tags; "%s" has %d.', self::MAX_TAGS, $item, \count($tags)));
        }

        $unique = [];

        foreach ($tags as $tag) {
            $unique[Text::code($tag, 'a tag')] = true;
        }

        $sorted = array_keys($unique);
        sort($sorted, \SORT_STRING);
        $this->tags = $sorted;
    }

    public function hasTag(string $tag): bool
    {
        return \in_array($tag, $this->tags, true);
    }
}
