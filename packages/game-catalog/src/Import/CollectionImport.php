<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

use Tilttek\GameCatalog\ExternalId;

/**
 * What an import found: the games of the catalogue, the identifiers the catalogue does not hold, the games of other
 * kinds than those imported, and the rows of the file left out.
 */
final readonly class CollectionImport
{
    /**
     * @param list<CollectionItem> $items      in the order of the file, one per entry
     * @param list<ExternalId>     $unmatched  no visible entry holds them: a member may propose the game
     * @param list<ExternalId>     $otherKinds their entry is of a kind not imported
     * @param int                  $duplicates rows of an entry already imported from an earlier row
     */
    public function __construct(
        public array $items,
        public array $unmatched,
        public array $otherKinds,
        public int $duplicates,
        public CollectionFile $file,
    ) {
    }
}
