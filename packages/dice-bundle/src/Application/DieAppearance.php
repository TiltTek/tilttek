<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Application;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * How a member's dice look: the name of the set, its colour, its material and an image. The appearance never changes
 * the randomness: it only dresses the dice the server rolled.
 */
final readonly class DieAppearance
{
    public function __construct(
        public string $name,
        public ?string $color = null,
        public ?string $material = null,
        public ?string $image = null,
    ) {
        if ('' === trim($name)) {
            throw InvalidDefinition::because('The appearance of a die has a name.');
        }

        if (null !== $color && 1 !== preg_match('/^#[0-9a-fA-F]{6}$/D', $color)) {
            throw InvalidDefinition::because(\sprintf('The colour of a die is written #rrggbb; "%s" is not.', $color));
        }
    }
}
