<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Contract;

use Phil\Resource\ResourceReference;

/**
 * The stickers each holder owns, by code: a holder owns a sticker once.
 */
interface OwnershipStore
{
    /**
     * Gives a sticker to a holder; returns false, writing nothing, when they own it already.
     */
    public function grant(ResourceReference $holder, string $sticker): bool;

    /**
     * Takes a sticker back from a holder; returns false when they did not own it.
     */
    public function revoke(ResourceReference $holder, string $sticker): bool;

    public function owns(ResourceReference $holder, string $sticker): bool;

    /**
     * The codes of the stickers a holder owns, in the order they were given.
     *
     * @return list<string>
     */
    public function owned(ResourceReference $holder): array;

    /**
     * Removes everything a holder owns; returns how many stickers.
     */
    public function eraseHolder(ResourceReference $holder): int;
}
