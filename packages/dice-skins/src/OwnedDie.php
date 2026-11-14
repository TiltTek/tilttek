<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

use Tilttek\DiceSkins\Exception\InvalidSkin;

/**
 * A die of a set a member owns, as the collection that holds it describes it: the item it is, its name, its number of
 * sides, its colour and its material, and the member's copy of it when the collection tells it.
 */
final readonly class OwnedDie
{
    /**
     * @throws InvalidSkin when the item or the name is empty, or the number of sides not that of a die
     */
    public function __construct(
        public string $item,
        public string $name,
        public int $sides,
        public ?Color $color = null,
        public ?string $material = null,
        public ?string $copy = null,
    ) {
        if ('' === trim($item) || '' === trim($name)) {
            throw InvalidSkin::because('A die a member owns has an item and a name.');
        }

        Skin::sides($sides);
    }

    /**
     * The look of the die: its colour, the black or white ink that stands out most on it — 4.58:1 at least —, and the
     * material its text names; null when the collection gives the die no colour.
     */
    public function look(): ?SkinLook
    {
        return $this->color instanceof Color ? new SkinLook($this->color, $this->color->bestInk(), Material::fromText($this->material)) : null;
    }
}
