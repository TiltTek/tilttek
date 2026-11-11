<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Dice;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\CollectibleDie;
use Tilttek\Collections\Dice\DiceShelf;
use Tilttek\Collections\Item;
use Tilttek\DiceBundle\Application\DieAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;

/**
 * The dice a member chose among those of their sets, as `tilttek/dice-bundle` dresses its rolls: the item's name in
 * the site's locale, the die's colour and material, and the item's image. Name this service in
 * `tilttek_dice.appearance`; the appearance never changes the randomness.
 */
final readonly class CollectionDiceAppearance implements DiceAppearance
{
    public function __construct(
        private DiceShelf $shelf,
        private string $locale,
    ) {
    }

    #[Override]
    public function appearance(ResourceReference $member, int $sides): ?DieAppearance
    {
        $item = $this->shelf->chosen($member, $sides);
        $die = $item?->die;

        if (!$item instanceof Item || !$die instanceof CollectibleDie) {
            return null;
        }

        return new DieAppearance($item->name->in($this->locale), $die->color, $die->material, $item->image);
    }
}
