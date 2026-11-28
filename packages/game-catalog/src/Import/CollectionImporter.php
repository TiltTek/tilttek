<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Import;

use Tilttek\GameCatalog\Contract\CatalogStore;
use Tilttek\GameCatalog\Entry;
use Tilttek\GameCatalog\GameKind;

/**
 * Finds the games of a collection file in the catalogue, by their identifiers. It imports the role-playing games
 * unless told other kinds; a game the catalogue does not hold, or hides, is returned by its identifier, for the member
 * to propose it — nothing is added to the catalogue from the file.
 */
final readonly class CollectionImporter
{
    public function __construct(private CatalogStore $store)
    {
    }

    /**
     * @param list<GameKind> $kinds the kinds of games imported
     */
    public function import(CollectionFile $file, array $kinds = [GameKind::RolePlaying]): CollectionImport
    {
        $items = [];
        $unmatched = [];
        $otherKinds = [];
        $duplicates = 0;

        foreach ($file->rows as $row) {
            $id = $this->store->findByIdentifier($row->identifier);
            $entry = null === $id ? null : $this->store->entry($id);

            if (!$entry instanceof Entry || $entry->hidden) {
                $unmatched[] = $row->identifier;
            } elseif (!\in_array($entry->details()->kind, $kinds, true)) {
                $otherKinds[] = $row->identifier;
            } elseif (isset($items[$entry->id])) {
                ++$duplicates;
            } else {
                $items[$entry->id] = new CollectionItem($entry->id, $row->statuses, $row->priority, $row->plays);
            }
        }

        return new CollectionImport(array_values($items), $unmatched, $otherKinds, $duplicates, $file);
    }
}
