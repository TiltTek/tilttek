<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Psr\Clock\ClockInterface;
use Tilttek\GameCatalog\Contract\BarcodeResolver;
use Tilttek\GameCatalog\Contract\CatalogObserver;
use Tilttek\GameCatalog\Contract\CatalogStore;
use Tilttek\GameCatalog\Contract\EntrySource;
use Tilttek\GameCatalog\Exception\CatalogConflict;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\Exception\InvalidEntry;

/**
 * The catalogue: entries entered by hand or sent by the sources that feed it, their identifiers in open databases,
 * their errata, the links between them, the barcodes of their boxes, and the page of each game. It keeps no state
 * between calls: everything is in the store.
 */
final readonly class Catalog
{
    public const int MAX_LINKS = 200;

    public const int MAX_SYNCHRONIZED = 10_000;

    public const int HISTORY = 50;

    public function __construct(
        private CatalogStore $store,
        private ClockInterface $clock,
        private ?CatalogObserver $observer = null,
        private ?BarcodeResolver $resolver = null,
    ) {
    }

    /**
     * Adds an entry entered by hand.
     *
     * @param list<ExternalId> $identifiers at most one of each database
     *
     * @throws InvalidEntry    when the identifier is not one, or the identifiers hold two of a database
     * @throws CatalogConflict when an entry has the identifier
     * @throws CatalogRefused  when another entry holds one of the identifiers
     */
    public function add(string $id, Details $details, array $identifiers = [], ?string $by = null): Entry
    {
        $entry = $this->store->save(new Entry($id, $details, Origin::manual(), $identifiers));
        $this->record($entry->id, ChangeKind::Created, $by);

        return $entry;
    }

    /**
     * @throws EntryNotFound when no entry has the identifier
     */
    public function get(string $id): Entry
    {
        return $this->store->entry($id);
    }

    /**
     * Adds the entry a source sends the first time, and updates it after: its details from the source, the
     * identifiers it did not have, the errata it did not have. The fields the administration corrected stay.
     *
     * @throws CatalogRefused when the entry would take an identifier another entry holds, or another identifier of a
     *                        database than the one it has
     */
    public function synchronize(SourcedEntry $sourced): Entry
    {
        $source = $sourced->source;
        $id = $this->store->findByOrigin($sourced->origin);

        if (null === $id) {
            $entry = $this->store->save(new Entry($sourced->id, $sourced->details, $sourced->origin, $sourced->identifiers, errata: $sourced->errata));
            $this->record($entry->id, ChangeKind::Created, $source);

            return $entry;
        }

        $entry = $this->store->entry($id);
        $fields = $entry->source->differences($sourced->details);
        $updated = $entry->withSource($sourced->details);

        foreach ($sourced->identifiers as $identifier) {
            $updated = $updated->withIdentifier($identifier);
        }

        $published = [];

        foreach ($sourced->errata as $erratum) {
            if (!$updated->erratum($erratum->id) instanceof Erratum) {
                $updated = $updated->withErratum($erratum);
                $published[] = $erratum->id;
            }
        }

        if ([] === $fields && $updated->identifiers === $entry->identifiers && [] === $published) {
            return $entry;
        }

        $entry = $this->store->save($updated);
        $this->record($entry->id, ChangeKind::Synchronized, $source, $fields);

        foreach ($published as $erratum) {
            $this->record($entry->id, ChangeKind::ErratumPublished, $source, about: $erratum);
            $this->observer?->erratumPublished($entry->id, $erratum);
        }

        return $entry;
    }

    /**
     * Synchronizes the entries of a source, up to the limit, and returns how many were read.
     *
     * @throws CatalogRefused when the source sends an entry under another name than its own
     */
    public function feed(EntrySource $source, int $limit = self::MAX_SYNCHRONIZED): int
    {
        $read = 0;

        foreach ($source->entries() as $sourced) {
            if ($read >= $limit) {
                break;
            }

            if ($sourced->source !== $source->name()) {
                throw CatalogRefused::because(\sprintf('The source %s sent an entry of the source %s.', $source->name(), $sourced->source));
            }

            $this->synchronize($sourced);
            ++$read;
        }

        return $read;
    }

    /**
     * Hides the entry from the catalogue: its page and its links stay, for the application to show or not.
     */
    public function hide(string $id, ?string $by = null): Entry
    {
        return $this->setHidden($id, true, ChangeKind::Hidden, $by);
    }

    public function show(string $id, ?string $by = null): Entry
    {
        return $this->setHidden($id, false, ChangeKind::Shown, $by);
    }

    /**
     * @throws CatalogRefused when the entry has another identifier of the database, or another entry holds this one
     */
    public function addIdentifier(string $id, ExternalId $identifier, ?string $by = null): Entry
    {
        $entry = $this->store->entry($id);
        $updated = $entry->withIdentifier($identifier);

        if ($updated === $entry) {
            return $entry;
        }

        $entry = $this->store->save($updated);
        $this->record($id, ChangeKind::IdentifierAdded, $by, about: $identifier->key());

        return $entry;
    }

    /**
     * Publishes an erratum on the entry, and tells the observer.
     *
     * @throws CatalogRefused when the entry has an erratum of that identifier, or has 500
     */
    public function publishErratum(string $id, Erratum $erratum, ?string $by = null): Entry
    {
        $entry = $this->store->save($this->store->entry($id)->withErratum($erratum));
        $this->record($id, ChangeKind::ErratumPublished, $by, about: $erratum->id);
        $this->observer?->erratumPublished($id, $erratum->id);

        return $entry;
    }

    /**
     * Links two entries, or changes the ordinal of their link.
     *
     * @throws EntryNotFound  when an entry of the link is not in the catalogue
     * @throws CatalogRefused when an entry of the link has 200 links
     */
    public function link(Link $link): void
    {
        foreach ([$link->from, $link->to] as $entry) {
            $this->store->entry($entry);
            $links = array_filter($this->store->links($entry), static fn (Link $held): bool => $held->key() !== $link->key());

            if (\count($links) >= self::MAX_LINKS) {
                throw CatalogRefused::because(\sprintf('The entry %s has %d links.', $entry, self::MAX_LINKS));
            }
        }

        $this->store->saveLink($link);
    }

    public function unlink(Link $link): void
    {
        $this->store->removeLink($link);
    }

    /**
     * Gives the barcode of a box to the entry, so that the next box scanned is identified locally.
     *
     * @throws EntryNotFound  when no entry has the identifier
     * @throws CatalogRefused when another entry has the barcode
     */
    public function assignBarcode(Gtin $barcode, string $id, ?string $by = null): void
    {
        $this->store->entry($id);

        if ($this->store->findByBarcode($barcode) === $id) {
            return;
        }

        $this->store->saveBarcode($barcode, $id);
        $this->record($id, ChangeKind::BarcodeAssigned, $by, about: $barcode->value);
    }

    /**
     * The entry of a scanned box: the barcode already given to an entry, else the identifier the resolver finds for
     * it, when an entry holds that identifier — the barcode is then given to the entry, and the resolver is not asked
     * again for it. `null` when neither knows the box.
     */
    public function identify(Gtin $barcode): ?Entry
    {
        $id = $this->store->findByBarcode($barcode);

        if (null !== $id) {
            return $this->store->entry($id);
        }

        $identifier = $this->resolver?->resolve($barcode);
        $id = $identifier instanceof ExternalId ? $this->store->findByIdentifier($identifier) : null;

        if (null === $id) {
            return null;
        }

        $this->assignBarcode($barcode, $id);

        return $this->store->entry($id);
    }

    public function findByIdentifier(ExternalId $identifier): ?Entry
    {
        $id = $this->store->findByIdentifier($identifier);

        return null === $id ? null : $this->store->entry($id);
    }

    /**
     * What the page of the game shows: the entry, the votes of the members, its links.
     *
     * @throws EntryNotFound when no entry has the identifier
     */
    public function sheet(string $id): Sheet
    {
        return new Sheet($this->store->entry($id), PollResults::count($this->store->ballots($id)), $this->store->links($id));
    }

    /**
     * The last changes of the entry, the latest first.
     *
     * @return list<Change>
     */
    public function history(string $id, int $limit = self::HISTORY): array
    {
        return $this->store->history($id, $limit);
    }

    private function setHidden(string $id, bool $hidden, ChangeKind $kind, ?string $by): Entry
    {
        $entry = $this->store->entry($id);

        if ($entry->hidden === $hidden) {
            return $entry;
        }

        $entry = $this->store->save($entry->withHidden($hidden));
        $this->record($id, $kind, $by);

        return $entry;
    }

    /**
     * @param list<Field> $fields
     */
    private function record(string $entry, ChangeKind $kind, ?string $by, array $fields = [], ?string $about = null): void
    {
        $this->store->record(new Change($entry, $kind, $this->clock->now(), $fields, $by, $about));
    }
}
