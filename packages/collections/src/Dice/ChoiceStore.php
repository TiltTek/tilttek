<?php

declare(strict_types=1);

namespace Tilttek\Collections\Dice;

use Phil\Resource\ResourceReference;

/**
 * Where the dice the members chose are kept, one per member and number of sides.
 */
interface ChoiceStore
{
    /**
     * Keeps a choice, in place of the member's choice for that number of sides.
     */
    public function save(DieChoice $choice): void;

    public function find(ResourceReference $member, int $sides): ?DieChoice;

    /**
     * Removes the member's choice for a number of sides; returns whether there was one.
     */
    public function remove(ResourceReference $member, int $sides): bool;

    /**
     * A member's choices, by number of sides.
     *
     * @return list<DieChoice>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Removes a member's choices; returns how many.
     */
    public function eraseMember(ResourceReference $member): int;
}
