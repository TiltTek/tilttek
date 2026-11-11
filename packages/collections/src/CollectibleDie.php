<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * The die an item is, for the items of a set of dice: its number of sides, its colour as `#rrggbb` and its material.
 * A member who owns it may choose it in the dice roller; the look never changes the randomness.
 */
final readonly class CollectibleDie
{
    public const int MAX_SIDES = 1000;

    public const int MAX_MATERIAL_LENGTH = 64;

    public function __construct(
        public int $sides,
        public ?string $color = null,
        public ?string $material = null,
    ) {
        if ($sides < 2 || $sides > self::MAX_SIDES) {
            throw InvalidCollection::because(\sprintf('A die has 2 to %d sides; this one has %d.', self::MAX_SIDES, $sides));
        }

        if (null !== $color && 1 !== preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
            throw InvalidCollection::because(\sprintf('The colour of a die is written #rrggbb; "%s" is not.', $color));
        }

        if (null !== $material && ('' === trim($material) || mb_strlen($material) > self::MAX_MATERIAL_LENGTH)) {
            throw InvalidCollection::because(\sprintf('The material of a die is 1 to %d characters.', self::MAX_MATERIAL_LENGTH));
        }
    }
}
