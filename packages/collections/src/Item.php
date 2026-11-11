<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * An item to collect — a virtual card, a die of a set, a figurine, a costume —: its code, its translated name and
 * description, its image, its rarity, the sets it belongs to, whether its copies may change hands, and the die it is
 * for the items of a set of dice.
 */
final readonly class Item
{
    public const int MAX_IMAGE_LENGTH = 2048;

    public const int MAX_SETS = 20;

    /**
     * @param list<string> $sets
     */
    public function __construct(
        public string $code,
        public Texts $name,
        public string $rarity,
        public array $sets = [],
        public ?Texts $description = null,
        public ?string $image = null,
        public bool $tradable = true,
        public ?CollectibleDie $die = null,
    ) {
        Names::check($code, 'item');
        Names::check($rarity, 'rarity');

        foreach ($sets as $set) {
            Names::check($set, 'set');
        }

        if (\count($sets) > self::MAX_SETS || \count(array_unique($sets)) !== \count($sets)) {
            throw InvalidCollection::because(\sprintf('"%s" belongs to at most %d sets, each named once.', $code, self::MAX_SETS));
        }

        if (null !== $image && ('' === $image || \strlen($image) > self::MAX_IMAGE_LENGTH || 1 === preg_match('/[\s\x00-\x1F\x7F]/', $image))) {
            throw InvalidCollection::because(\sprintf('The image of "%s" is a path or a URL of 1 to %d bytes without whitespace.', $code, self::MAX_IMAGE_LENGTH));
        }
    }

    public function belongsTo(string $set): bool
    {
        return \in_array($set, $this->sets, true);
    }
}
