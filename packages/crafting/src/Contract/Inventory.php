<?php

declare(strict_types=1);

namespace Tilttek\Crafting\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\Crafting\Craft;
use Tilttek\Crafting\Exception\CraftRefused;
use Tilttek\Crafting\Holding;

/**
 * The items of the holders, as the application keeps them — the copies of `tilttek/collections`, a character's
 * inventory, the stock of a larp's economy —, and the crafts applied to them, by key.
 */
interface Inventory
{
    /**
     * What a holder has, one holding per item, with the tags of each item.
     *
     * @return iterable<Holding>
     */
    public function holdings(ResourceReference $holder): iterable;

    /**
     * The craft applied under a key, if any.
     */
    public function find(string $key): ?Craft;

    /**
     * Applies a craft at once, or not at all: removes the items it consumes, adds those it gives back and produces,
     * and records it under its key. Returns false and changes nothing when a craft is already recorded under the key.
     *
     * @throws CraftRefused when the holder no longer has the items the craft consumes or uses; nothing changed
     */
    public function apply(Craft $craft): bool;
}
