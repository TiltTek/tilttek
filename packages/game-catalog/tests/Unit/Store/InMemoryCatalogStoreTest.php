<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Store;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Change;
use Tilttek\GameCatalog\ChangeKind;
use Tilttek\GameCatalog\Correction;
use Tilttek\GameCatalog\CorrectionStatus;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\Entry;
use Tilttek\GameCatalog\Exception\CatalogConflict;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\CorrectionNotFound;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Gtin;
use Tilttek\GameCatalog\Link;
use Tilttek\GameCatalog\LinkType;
use Tilttek\GameCatalog\Origin;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;

#[CoversClass(InMemoryCatalogStore::class)]
#[CoversClass(EntryNotFound::class)]
#[CoversClass(CorrectionNotFound::class)]
#[CoversClass(CatalogConflict::class)]
final class InMemoryCatalogStoreTest extends TestCase
{
    private InMemoryCatalogStore $store;

    protected function setUp(): void
    {
        $this->store = new InMemoryCatalogStore();
    }

    public function testAnEntryIsKeptAtTheNextVersion(): void
    {
        $saved = $this->store->save(new Entry('e-1', new Details('Tide'), Origin::manual()));

        self::assertSame(1, $saved->version);
        self::assertSame($saved, $this->store->entry('e-1'));
        self::assertSame(2, $this->store->save($saved->withHidden(true))->version);
        self::assertTrue($this->store->entry('e-1')->hidden);
    }

    public function testAnEntryReadBeforeAChangeIsNotKept(): void
    {
        $saved = $this->store->save(new Entry('e-1', new Details('Tide'), Origin::manual()));
        $this->store->save($saved);

        try {
            $this->store->save($saved->withHidden(true));
            self::fail('A stale entry was kept.');
        } catch (CatalogConflict $catalogConflict) {
            self::assertSame('The entry e-1 changed since it was read.', $catalogConflict->getMessage());
        }

        self::assertFalse($this->store->entry('e-1')->hidden);
        $this->expectException(CatalogConflict::class);
        $this->store->save(new Entry('e-1', new Details('Tide'), Origin::manual()));
    }

    public function testAnEntryNotKeptIsNotFound(): void
    {
        $this->expectException(EntryNotFound::class);
        $this->expectExceptionMessageIs('No entry of the catalogue has the identifier e-9.');
        $this->store->entry('e-9');
    }

    public function testAnEntryIsFoundByItsIdentifiersAndItsOrigin(): void
    {
        $this->store->save(new Entry('e-1', new Details('Tide'), Origin::source('books', 'b1'), [ExternalId::rpgGeek(7), ExternalId::wikidata('Q7')]));
        $this->store->save(new Entry('e-2', new Details('Ebb'), Origin::manual()));

        self::assertSame('e-1', $this->store->findByIdentifier(ExternalId::rpgGeek(7)));
        self::assertSame('e-1', $this->store->findByIdentifier(ExternalId::wikidata('Q7')));
        self::assertNull($this->store->findByIdentifier(ExternalId::boardGameGeek(7)));
        self::assertSame('e-1', $this->store->findByOrigin(Origin::source('books', 'b1')));
        self::assertNull($this->store->findByOrigin(Origin::source('books', 'b2')));
        self::assertNull($this->store->findByOrigin(Origin::manual()));
    }

    public function testAnIdentifierIsHeldByOneEntry(): void
    {
        $this->store->save(new Entry('e-1', new Details('Tide'), Origin::manual(), [ExternalId::rpgGeek(7)]));

        try {
            $this->store->save(new Entry('e-2', new Details('Ebb'), Origin::manual(), [ExternalId::wikidata('Q2'), ExternalId::rpgGeek(7)]));
            self::fail('Two entries hold the same identifier.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame('The entry e-1 already holds rpggeek:7.', $catalogRefused->getMessage());
        }

        self::assertNull($this->store->findByIdentifier(ExternalId::wikidata('Q2')));
        $this->expectException(EntryNotFound::class);
        $this->store->entry('e-2');
    }

    public function testAnOriginIsHeldByOneEntry(): void
    {
        $this->store->save(new Entry('e-1', new Details('Tide'), Origin::source('books', 'b1')));

        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIs('The entry e-1 already holds books/b1.');
        $this->store->save(new Entry('e-2', new Details('Ebb'), Origin::source('books', 'b1')));
    }

    public function testABarcodeIsGivenToOneEntry(): void
    {
        $barcode = new Gtin('4006381333931');

        self::assertNull($this->store->findByBarcode($barcode));
        $this->store->saveBarcode($barcode, 'e-1');
        $this->store->saveBarcode($barcode, 'e-1');
        self::assertSame('e-1', $this->store->findByBarcode(new Gtin('04006381333931')));

        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIs('The barcode 04006381333931 is given to the entry e-1.');
        $this->store->saveBarcode($barcode, 'e-2');
    }

    public function testLinksAreFoundFromBothEntries(): void
    {
        $this->store->saveLink(new Link('e-2', LinkType::Expands, 'e-1', 1));
        $this->store->saveLink(new Link('e-2', LinkType::Expands, 'e-1', 2));
        $this->store->saveLink(new Link('e-3', LinkType::IntegratesWith, 'e-2'));
        $this->store->saveLink(new Link('e-4', LinkType::EditionOf, 'e-5'));

        self::assertSame([2], array_map(static fn (Link $link): ?int => $link->ordinal, $this->store->links('e-1')));
        self::assertCount(2, $this->store->links('e-2'));
        self::assertSame([], $this->store->links('e-9'));

        $this->store->removeLink(new Link('e-2', LinkType::IntegratesWith, 'e-3'));
        $this->store->removeLink(new Link('e-7', LinkType::IntegratesWith, 'e-8'));
        self::assertCount(1, $this->store->links('e-2'));
        self::assertSame('e-4', $this->store->links('e-5')[0]->from);
    }

    public function testCorrectionsAreKeptAtTheirVersionAndListed(): void
    {
        $at = new DateTimeImmutable();
        $first = $this->store->saveCorrection(new Correction('c-1', 'e-1', 'm-1', new Details('A'), new Details('B'), $at));
        $this->store->saveCorrection(new Correction('c-2', 'e-2', 'm-1', new Details('A'), new Details('B'), $at));
        $this->store->saveCorrection(new Correction('c-3', 'e-1', 'm-2', new Details('A'), new Details('B'), $at));

        $decided = $this->store->saveCorrection($first->decided(CorrectionStatus::Refused, 'admin', $at));

        self::assertSame(1, $first->version);
        self::assertSame(2, $decided->version);
        self::assertSame($decided, $this->store->correction('c-1'));
        $ids = static fn (array $corrections): array => array_map(static fn (Correction $correction): string => $correction->id, $corrections);
        self::assertSame(['c-1', 'c-2', 'c-3'], $ids($this->store->corrections()));
        self::assertSame(['c-1', 'c-3'], $ids($this->store->corrections('e-1')));
        self::assertSame(['c-1', 'c-2'], $ids($this->store->corrections(member: 'm-1')));
        self::assertSame(['c-2', 'c-3'], $ids($this->store->corrections(status: CorrectionStatus::Pending)));
        self::assertSame(['c-3'], $ids($this->store->corrections('e-1', 'm-2', CorrectionStatus::Pending)));
        self::assertSame([], $ids($this->store->corrections('e-2', 'm-2')));

        try {
            $this->store->saveCorrection($first->decided(CorrectionStatus::Accepted, 'admin', $at));
            self::fail('A stale correction was kept.');
        } catch (CatalogConflict $catalogConflict) {
            self::assertSame('The correction c-1 changed since it was read.', $catalogConflict->getMessage());
        }

        $this->expectException(CatalogConflict::class);
        $this->store->saveCorrection(new Correction('c-1', 'e-1', 'm-1', new Details('A'), new Details('C'), $at));
    }

    public function testACorrectionNotKeptIsNotFound(): void
    {
        $this->expectException(CorrectionNotFound::class);
        $this->expectExceptionMessageIs('No correction has the identifier c-9.');
        $this->store->correction('c-9');
    }

    public function testAMemberHasOneBallotPerEntry(): void
    {
        $this->store->saveBallot(new Ballot('e-1', 'm-1', weight: 2));
        $this->store->saveBallot(new Ballot('e-1', 'm-1', weight: 4));
        $this->store->saveBallot(new Ballot('e-1', 'm-2', weight: 1));
        $this->store->saveBallot(new Ballot('e-2', 'm-1', weight: 5));

        self::assertSame(4, $this->store->ballot('e-1', 'm-1')?->weight);
        self::assertNull($this->store->ballot('e-3', 'm-1'));
        self::assertSame([4, 1], array_map(static fn (Ballot $ballot): ?int => $ballot->weight, [...$this->store->ballots('e-1')]));
        self::assertSame([], [...$this->store->ballots('e-3')]);

        $this->store->removeBallot('e-1', 'm-1');
        $this->store->removeBallot('e-3', 'm-1');
        self::assertNull($this->store->ballot('e-1', 'm-1'));
        self::assertSame([0], array_keys([...$this->store->ballots('e-1')]));
    }

    public function testTheHistoryIsReadTheLatestFirst(): void
    {
        $at = new DateTimeImmutable();
        $this->store->record(new Change('e-1', ChangeKind::Created, $at));
        $this->store->record(new Change('e-2', ChangeKind::Created, $at));
        $this->store->record(new Change('e-1', ChangeKind::Hidden, $at, by: 'admin', about: 'why'));
        $this->store->record(new Change('e-1', ChangeKind::Shown, $at));

        $kinds = static fn (array $changes): array => array_map(static fn (Change $change): ChangeKind => $change->kind, $changes);
        self::assertSame([ChangeKind::Shown, ChangeKind::Hidden, ChangeKind::Created], $kinds($this->store->history('e-1', 10)));
        self::assertSame([ChangeKind::Shown, ChangeKind::Hidden], $kinds($this->store->history('e-1', 2)));
        self::assertSame([], $this->store->history('e-1', 0));
        self::assertSame([], $this->store->history('e-1', -1));
        self::assertSame([], $this->store->history('e-9', 5));
        self::assertSame('admin', $this->store->history('e-1', 2)[1]->by);
        self::assertSame('why', $this->store->history('e-1', 2)[1]->about);
        self::assertSame($at, $this->store->history('e-1', 2)[1]->at);
        self::assertSame([], $this->store->history('e-1', 2)[1]->fields);
    }
}
