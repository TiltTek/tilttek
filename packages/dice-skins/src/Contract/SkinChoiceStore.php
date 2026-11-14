<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\SkinChoice;

/**
 * Where the skins the members chose are kept: one per member for all their dice, and one per member and number of
 * sides.
 */
interface SkinChoiceStore
{
    /**
     * Keeps a choice, in place of the member's choice for the same number of sides, or for all their dice.
     */
    public function save(SkinChoice $choice): void;

    /**
     * The member's choice for a number of sides, or for all their dice with `$sides` null.
     */
    public function find(ResourceReference $member, ?int $sides): ?SkinChoice;

    /**
     * Removes the member's choice for a number of sides, or for all their dice; returns whether there was one.
     */
    public function remove(ResourceReference $member, ?int $sides): bool;

    /**
     * The member's choices: the one for all their dice first, then by number of sides.
     *
     * @return list<SkinChoice>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Removes every choice of a member; returns how many.
     */
    public function eraseMember(ResourceReference $member): int;
}
