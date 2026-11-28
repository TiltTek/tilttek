<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

/**
 * What an import changed on a shelf, game by game of the file.
 */
final readonly class ShelfImport
{
    /**
     * @param int          $copiesAdded           copies placed, one per game owned, held, preordered or for trade
     * @param int          $copiesUpdated         copies imported before whose statuses changed
     * @param int          $wishesAdded           games wished
     * @param int          $wishesUpdated         wishes whose kinds or level changed
     * @param int          $playsUpdated          notes whose number of plays changed
     * @param int          $unchanged             games of the file that changed nothing
     * @param list<string> $refused               games left out: their statuses do not go together, or the shelf is full
     * @param int          $copiesPreviouslyOwned imported copies the file no longer names, moved to previously owned by a
     *                                            synchronized import
     */
    public function __construct(
        public Shelf $shelf,
        public int $copiesAdded = 0,
        public int $copiesUpdated = 0,
        public int $wishesAdded = 0,
        public int $wishesUpdated = 0,
        public int $playsUpdated = 0,
        public int $unchanged = 0,
        public array $refused = [],
        public int $copiesPreviouslyOwned = 0,
    ) {
    }
}
