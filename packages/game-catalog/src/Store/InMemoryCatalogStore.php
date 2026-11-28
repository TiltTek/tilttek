<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Store;

use Override;
use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Change;
use Tilttek\GameCatalog\Contract\CatalogStore;
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
 * The catalogue in memory, for tests: it lasts as long as the object.
 */
final class InMemoryCatalogStore implements CatalogStore
{
    /** @var array<string, Entry> by entry */
    private array $entries = [];

    /** @var array<string, string> entries by identifier key, origin key or barcode */
    private array $index = [];

    /** @var array<string, Link> by key */
    private array $links = [];

    /** @var array<string, Correction> by correction, in the order they were proposed */
    private array $corrections = [];

    /** @var array<string, array<string, Ballot>> by entry, then member */
    private array $ballots = [];

    /** @var array<string, list<Change>> by entry, the oldest first */
    private array $history = [];

    #[Override]
    public function entry(string $id): Entry
    {
        return $this->entries[$id] ?? throw EntryNotFound::withId($id);
    }

    #[Override]
    public function save(Entry $entry): Entry
    {
        if ($entry->version !== ($this->entries[$entry->id]->version ?? 0)) {
            throw CatalogConflict::changed('The entry '.$entry->id);
        }

        $held = [];

        foreach ($entry->identifiers as $identifier) {
            $held['id '.$identifier->key()] = $identifier->key();
        }

        $origin = $entry->origin->key();

        if (null !== $origin) {
            $held['origin '.$origin] = $origin;
        }

        foreach ($held as $key => $what) {
            $holder = $this->index[$key] ?? $entry->id;

            if ($holder !== $entry->id) {
                throw CatalogRefused::because(\sprintf('The entry %s already holds %s.', $holder, $what));
            }
        }

        foreach (array_keys($held) as $key) {
            $this->index[$key] = $entry->id;
        }

        return $this->entries[$entry->id] = $entry->withVersion($entry->version + 1);
    }

    #[Override]
    public function findByIdentifier(ExternalId $identifier): ?string
    {
        return $this->index['id '.$identifier->key()] ?? null;
    }

    #[Override]
    public function findByOrigin(Origin $origin): ?string
    {
        return $this->index['origin '.$origin->key()] ?? null;
    }

    #[Override]
    public function findByBarcode(Gtin $barcode): ?string
    {
        return $this->index['gtin '.$barcode->value] ?? null;
    }

    #[Override]
    public function saveBarcode(Gtin $barcode, string $entry): void
    {
        $holder = $this->index['gtin '.$barcode->value] ?? $entry;

        if ($holder !== $entry) {
            throw CatalogRefused::because(\sprintf('The barcode %s is given to the entry %s.', $barcode->value, $holder));
        }

        $this->index['gtin '.$barcode->value] = $entry;
    }

    #[Override]
    public function links(string $entry): array
    {
        return array_values(array_filter(
            $this->links,
            static fn (Link $link): bool => $link->from === $entry || $link->to === $entry,
        ));
    }

    #[Override]
    public function saveLink(Link $link): void
    {
        $this->links[$link->key()] = $link;
    }

    #[Override]
    public function removeLink(Link $link): void
    {
        unset($this->links[$link->key()]);
    }

    #[Override]
    public function correction(string $id): Correction
    {
        return $this->corrections[$id] ?? throw CorrectionNotFound::withId($id);
    }

    #[Override]
    public function saveCorrection(Correction $correction): Correction
    {
        if ($correction->version !== ($this->corrections[$correction->id]->version ?? 0)) {
            throw CatalogConflict::changed('The correction '.$correction->id);
        }

        return $this->corrections[$correction->id] = $correction->withVersion($correction->version + 1);
    }

    #[Override]
    public function corrections(?string $entry = null, ?string $member = null, ?CorrectionStatus $status = null): array
    {
        return array_values(array_filter(
            $this->corrections,
            static fn (Correction $correction): bool => (null === $entry || $correction->entry === $entry)
                && (null === $member || $correction->member === $member)
                && (!$status instanceof CorrectionStatus || $correction->status === $status),
        ));
    }

    #[Override]
    public function ballot(string $entry, string $member): ?Ballot
    {
        return $this->ballots[$entry][$member] ?? null;
    }

    #[Override]
    public function saveBallot(Ballot $ballot): void
    {
        $this->ballots[$ballot->entry][$ballot->member] = $ballot;
    }

    #[Override]
    public function removeBallot(string $entry, string $member): void
    {
        unset($this->ballots[$entry][$member]);
    }

    #[Override]
    public function ballots(string $entry): iterable
    {
        return array_values($this->ballots[$entry] ?? []);
    }

    #[Override]
    public function record(Change $change): void
    {
        $this->history[$change->entry][] = $change;
    }

    #[Override]
    public function history(string $entry, int $limit): array
    {
        return \array_slice(array_reverse($this->history[$entry] ?? []), 0, max(0, $limit));
    }
}
