<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\Stickers\Placement;

/**
 * The placements of the stickers. A store shared by several processes counts and adds in one transaction, so that two
 * placements at the same moment never pass a limit together.
 */
interface PlacementStore
{
    public function add(Placement $placement): void;

    /**
     * Writes a placement over the one of the same identifier.
     */
    public function replace(Placement $placement): void;

    public function get(string $id): ?Placement;

    /**
     * Removes a placement; returns false when there was none of that identifier.
     */
    public function remove(string $id): bool;

    /**
     * The placements of a surface, hidden ones included, in the order they were placed: the latest is drawn on top.
     *
     * @return list<Placement>
     */
    public function onSurface(ResourceReference $surface): array;

    /**
     * The placements of a holder, hidden ones included, in the order they were placed.
     *
     * @return list<Placement>
     */
    public function ofHolder(ResourceReference $holder): array;

    /**
     * Removes the placements of a holder; returns how many.
     */
    public function eraseHolder(ResourceReference $holder): int;

    /**
     * Removes the placements of a surface, for a profile or a message deleted; returns how many.
     */
    public function eraseSurface(ResourceReference $surface): int;
}
