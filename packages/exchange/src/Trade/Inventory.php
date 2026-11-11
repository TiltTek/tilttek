<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\ExchangeRefused;

/**
 * The members' items, as the application's collections keep them — virtual cards, the dice of a set —: who owns an
 * item, whether it may change hands, and the moves of an accepted trade. Items and members are opaque references.
 */
interface Inventory
{
    /**
     * The member who owns the item; null when no one does.
     */
    public function ownerOf(ResourceReference $item): ?ResourceReference;

    /**
     * Whether the item may change hands: an item earned by a quest may stay bound to its member.
     */
    public function isTradable(ResourceReference $item): bool;

    /**
     * Moves the items, all of them or none, each from the member who owns it to another.
     *
     * @param list<ItemMove> $moves
     *
     * @throws ExchangeRefused with `Refusal::NotOwned` when an item no longer belongs to the member who gives it
     */
    public function move(array $moves): void;
}
