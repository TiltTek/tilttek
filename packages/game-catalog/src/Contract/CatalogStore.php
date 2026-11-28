<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Contract;

use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Change;
use Tilttek\GameCatalog\Correction;
use Tilttek\GameCatalog\CorrectionStatus;
use Tilttek\GameCatalog\Entry;
use Tilttek\GameCatalog\Exception\CatalogConflict;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\CorrectionNotFound;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Gtin;
use Tilttek\GameCatalog\Link;
use Tilttek\GameCatalog\Origin;

/**
 * Where the catalogue is kept: the entries, each at a version, with the indexes that find them by an identifier of an
 * open database, by their source and by the barcode of a box; the links between entries; the corrections; the ballots;
 * the history. `Store\InMemoryCatalogStore` keeps them in memory, for tests; an application keeps them in its
 * database.
 */
interface CatalogStore
{
    /**
     * @throws EntryNotFound when no entry has the identifier
     */
    public function entry(string $id): Entry;

    /**
     * Keeps the entry at the next version, with its identifiers and its origin indexed: an entry at version 0 is new,
     * and the store has no entry of its identifier; any other is kept only when the store holds it at that version.
     *
     * @throws CatalogConflict when the entry changed or was added since it was read; nothing is kept
     * @throws CatalogRefused  when another entry holds one of its identifiers or its origin; nothing is kept
     */
    public function save(Entry $entry): Entry;

    /**
     * The entry that holds the identifier, if one does.
     */
    public function findByIdentifier(ExternalId $identifier): ?string;

    /**
     * The entry its source sent under this origin, if it did.
     */
    public function findByOrigin(Origin $origin): ?string;

    /**
     * The entry the barcode was given to, if it was.
     */
    public function findByBarcode(Gtin $barcode): ?string;

    /**
     * Gives the barcode to the entry; a barcode is given to one entry at most.
     *
     * @throws CatalogRefused when another entry has the barcode
     */
    public function saveBarcode(Gtin $barcode, string $entry): void;

    /**
     * The links from and to the entry.
     *
     * @return list<Link>
     */
    public function links(string $entry): array;

    /**
     * Keeps the link, or replaces the link of the same key.
     */
    public function saveLink(Link $link): void;

    /**
     * Removes the link of the same key; nothing when there is none.
     */
    public function removeLink(Link $link): void;

    /**
     * @throws CorrectionNotFound when no correction has the identifier
     */
    public function correction(string $id): Correction;

    /**
     * Keeps the correction at the next version, as `save()` keeps an entry.
     *
     * @throws CatalogConflict when the correction changed or was added since it was read; nothing is kept
     */
    public function saveCorrection(Correction $correction): Correction;

    /**
     * The corrections of an entry, or of a member, in the order they were proposed, of a status or of all.
     *
     * @return list<Correction>
     */
    public function corrections(?string $entry = null, ?string $member = null, ?CorrectionStatus $status = null): array;

    public function ballot(string $entry, string $member): ?Ballot;

    /**
     * Keeps the ballot in place of the member's ballot on the entry.
     */
    public function saveBallot(Ballot $ballot): void;

    public function removeBallot(string $entry, string $member): void;

    /**
     * The ballots on the entry, one per member.
     *
     * @return iterable<Ballot>
     */
    public function ballots(string $entry): iterable;

    /**
     * Adds a line to the history of its entry.
     */
    public function record(Change $change): void;

    /**
     * The last lines of the history of the entry, the latest first.
     *
     * @return list<Change>
     */
    public function history(string $entry, int $limit): array;
}
