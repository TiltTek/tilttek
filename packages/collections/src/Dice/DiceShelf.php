<?php

declare(strict_types=1);

namespace Tilttek\Collections\Dice;

use Phil\Resource\ResourceReference;
use Tilttek\Collections\CollectibleDie;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\Item;

/**
 * The dice a member owns — the items of a set of dice —, and the one they choose to roll for each number of sides. A
 * die traded away is no longer rolled: the choice falls back to the plain dice until the member chooses another.
 */
final readonly class DiceShelf
{
    public function __construct(
        private Collections $collections,
        private ChoiceStore $choices,
    ) {
    }

    /**
     * The member's copies that are dice.
     *
     * @return list<Copy>
     */
    public function dice(ResourceReference $member): array
    {
        $catalog = $this->collections->catalog->catalog();

        return array_values(array_filter(
            $this->collections->copiesOf($member),
            static fn (Copy $copy): bool => $catalog->item($copy->item)->die instanceof CollectibleDie,
        ));
    }

    /**
     * Chooses one of the member's dice for its number of sides.
     *
     * @throws NotFound          when no copy has the identifier
     * @throws CollectionRefused when the copy is not the member's, or not a die
     */
    public function choose(ResourceReference $member, string $copy): DieChoice
    {
        $chosen = $this->collections->copy($copy);
        $die = $this->collections->catalog->catalog()->item($chosen->item)->die;

        if (!$chosen->owner->equals($member)) {
            throw CollectionRefused::because(\sprintf('The copy "%s" does not belong to "%s".', $copy, $member->toString()));
        }

        if (!$die instanceof CollectibleDie) {
            throw CollectionRefused::because(\sprintf('"%s" is not a die.', $chosen->item));
        }

        $choice = new DieChoice($member, $die->sides, $copy);
        $this->choices->save($choice);

        return $choice;
    }

    /**
     * Goes back to the plain dice for a number of sides; returns whether a die was chosen.
     */
    public function clear(ResourceReference $member, int $sides): bool
    {
        return $this->choices->remove($member, $sides);
    }

    /**
     * The item of the die the member rolls for a number of sides, if they chose one they still own.
     */
    public function chosen(ResourceReference $member, int $sides): ?Item
    {
        $choice = $this->choices->find($member, $sides);
        $copy = $choice instanceof DieChoice ? $this->collections->copies->find($choice->copy) : null;

        if (!$copy instanceof Copy || !$copy->owner->equals($member)) {
            return null;
        }

        return $this->collections->catalog->catalog()->item($copy->item);
    }
}
