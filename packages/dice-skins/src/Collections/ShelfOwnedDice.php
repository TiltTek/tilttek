<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Collections;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\CollectibleDie;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Dice\DiceShelf;
use Tilttek\Collections\Item;
use Tilttek\DiceSkins\Color;
use Tilttek\DiceSkins\Contract\OwnedDice;
use Tilttek\DiceSkins\OwnedDie;

/**
 * The owned dice read from `tilttek/collections`: the member's copies that are dice, and the die they chose on their
 * shelf for a number of sides, named in the locale given.
 */
final readonly class ShelfOwnedDice implements OwnedDice
{
    public function __construct(
        private Collections $collections,
        private DiceShelf $shelf,
        private string $locale,
    ) {
    }

    #[Override]
    public function ownedBy(ResourceReference $member): array
    {
        $catalog = $this->collections->catalog->catalog();
        $dice = [];

        foreach ($this->shelf->dice($member) as $copy) {
            $die = $this->die($catalog->item($copy->item), $copy->id);

            if ($die instanceof OwnedDie) {
                $dice[] = $die;
            }
        }

        return $dice;
    }

    #[Override]
    public function chosen(ResourceReference $member, int $sides): ?OwnedDie
    {
        $item = $this->shelf->chosen($member, $sides);

        return $item instanceof Item ? $this->die($item, null) : null;
    }

    private function die(Item $item, ?string $copy): ?OwnedDie
    {
        $die = $item->die;

        if (!$die instanceof CollectibleDie) {
            return null;
        }

        return new OwnedDie(
            $item->code,
            $item->name->in($this->locale),
            $die->sides,
            null === $die->color ? null : Color::fromHex($die->color),
            $die->material,
            $copy,
        );
    }
}
