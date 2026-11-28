<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Contract;

use Tilttek\GameShelf\Exception\ShelfConflict;
use Tilttek\GameShelf\Shelf;

/**
 * Keeps the shelves, one per member, each at a version.
 */
interface ShelfStore
{
    /**
     * The shelf of the member, or an empty shelf at version 0 when none is kept.
     */
    public function shelf(string $member): Shelf;

    /**
     * Keeps the shelf at the next version, and returns it. A shelf at version 0 is new; any other is kept only when
     * the store holds it at that version.
     *
     * @throws ShelfConflict when the shelf is new and the store has one for its member, or the store holds it at
     *                       another version; nothing is kept
     */
    public function save(Shelf $shelf): Shelf;
}
