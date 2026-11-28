<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit;

use DateTimeImmutable;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Change;
use Tilttek\GameCatalog\ChangeKind;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\EntryType;
use Tilttek\GameCatalog\Erratum;
use Tilttek\GameCatalog\Exception\CatalogConflict;
use Tilttek\GameCatalog\Exception\CatalogRefused;
use Tilttek\GameCatalog\Exception\EntryNotFound;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Field;
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\Gtin;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Link;
use Tilttek\GameCatalog\LinkType;
use Tilttek\GameCatalog\SourcedEntry;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameCatalog\Tests\Unit\Fixture\ListedSource;
use Tilttek\GameCatalog\Tests\Unit\Fixture\MappedResolver;
use Tilttek\GameCatalog\Tests\Unit\Fixture\Observed;

#[CoversClass(Catalog::class)]
#[CoversClass(Change::class)]
#[CoversClass(CatalogRefused::class)]
final class CatalogTest extends TestCase
{
    private InMemoryCatalogStore $store;

    private MockClock $clock;

    private Observed $observed;

    private Catalog $catalog;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryCatalogStore();
        $this->clock = new MockClock(new DateTimeImmutable('2027-03-01 10:00 UTC'));
        $this->observed = new Observed();
        $this->catalog = new Catalog($this->store, $this->clock, $this->observed);
    }

    public function testAnEntryIsAddedByHand(): void
    {
        $entry = $this->catalog->add('e-1', new Details('Tide'), [ExternalId::boardGameGeek(13)], 'admin');

        self::assertSame(1, $entry->version);
        self::assertTrue($entry->origin->isManual());
        self::assertSame($entry, $this->catalog->get('e-1'));
        self::assertSame($entry, $this->catalog->findByIdentifier(ExternalId::boardGameGeek(13)));
        self::assertNull($this->catalog->findByIdentifier(ExternalId::boardGameGeek(14)));
        $history = $this->catalog->history('e-1');
        self::assertCount(1, $history);
        self::assertSame(ChangeKind::Created, $history[0]->kind);
        self::assertSame('admin', $history[0]->by);
        self::assertEquals($this->clock->now(), $history[0]->at);
        self::assertNull($this->catalog->add('e-2', new Details('Ebb'))->identifier(IdentifierScheme::BoardGameGeek));
        self::assertNull($this->catalog->history('e-2')[0]->by);

        $this->expectException(CatalogConflict::class);
        $this->catalog->add('e-1', new Details('Tide again'));
    }

    public function testTheHistoryIsLimited(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));

        for ($i = 0; $i < Catalog::HISTORY + 5; ++$i) {
            $this->catalog->hide('e-1');
            $this->catalog->show('e-1');
        }

        self::assertCount(50, $this->catalog->history('e-1'));
        self::assertCount(3, $this->catalog->history('e-1', 3));
    }

    public function testASourceAddsAnEntryThenUpdatesIt(): void
    {
        $erratum = new Erratum('er-1', 'p. 4', 'One round.', new DateTimeImmutable('2027-01-01'));
        $first = $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide', kind: GameKind::RolePlaying), [ExternalId::rpgGeek(3)], [$erratum]));

        self::assertSame('e-1', $first->id);
        self::assertSame('books/b-1', $first->origin->key());
        self::assertSame([$erratum], $first->errata);
        self::assertSame([], $this->observed->calls);
        self::assertSame('books', $this->catalog->history('e-1')[0]->by);

        $same = $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'ignored', new Details('Tide', kind: GameKind::RolePlaying), [ExternalId::rpgGeek(3)], [$erratum]));
        self::assertSame($first, $same);
        self::assertCount(1, $this->catalog->history('e-1'));

        $second = new Erratum('er-2', 'p. 9', 'Two rounds.', new DateTimeImmutable('2027-02-01'));
        $updated = $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'ignored', new Details('Tide, revised', kind: GameKind::RolePlaying, year: 2027), [ExternalId::rpgGeek(3), ExternalId::wikidata('Q3')], [$erratum, $second]));

        self::assertSame('e-1', $updated->id);
        self::assertSame(2, $updated->version);
        self::assertSame('Tide, revised', $updated->details()->name);
        self::assertSame(['rpggeek:3', 'wikidata:Q3'], array_map(static fn (ExternalId $id): string => $id->key(), $updated->identifiers));
        self::assertSame([$erratum, $second], $updated->errata);
        self::assertSame(['erratum e-1 er-2'], $this->observed->calls);

        $history = $this->catalog->history('e-1');
        self::assertSame(ChangeKind::ErratumPublished, $history[0]->kind);
        self::assertSame('er-2', $history[0]->about);
        self::assertSame('books', $history[0]->by);
        self::assertSame(ChangeKind::Synchronized, $history[1]->kind);
        self::assertSame([Field::Name, Field::Year], $history[1]->fields);
        self::assertSame('books', $history[1]->by);
    }

    public function testAnIdentifierAloneIsASynchronization(): void
    {
        $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide')));
        $updated = $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide'), [ExternalId::wikidata('Q3')]));

        self::assertSame(2, $updated->version);
        self::assertSame([], $this->catalog->history('e-1')[0]->fields);
    }

    public function testAnErratumAloneIsASynchronization(): void
    {
        $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide')));
        $updated = $this->catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide'), errata: [new Erratum('er-1', 'p', 't', new DateTimeImmutable())]));

        self::assertSame(2, $updated->version);
        self::assertSame(['erratum e-1 er-1'], $this->observed->calls);
    }

    public function testASourceFeedsUpToTheLimit(): void
    {
        $source = new ListedSource('books', [
            new SourcedEntry('books', 'b-1', 'e-1', new Details('One')),
            new SourcedEntry('books', 'b-2', 'e-2', new Details('Two')),
            new SourcedEntry('books', 'b-3', 'e-3', new Details('Three')),
        ]);

        self::assertSame(2, $this->catalog->feed($source, 2));
        self::assertSame('Two', $this->catalog->get('e-2')->details()->name);
        self::assertSame(3, $source->read);
        $this->expectException(EntryNotFound::class);
        $this->catalog->get('e-3');
    }

    public function testASourceIsNotReadPastTheLimit(): void
    {
        $source = new ListedSource('books', [
            new SourcedEntry('books', 'b-1', 'e-1', new Details('One')),
            new SourcedEntry('books', 'b-2', 'e-2', new Details('Two')),
            new SourcedEntry('books', 'b-3', 'e-3', new Details('Three')),
            new SourcedEntry('books', 'b-4', 'e-4', new Details('Four')),
        ]);

        self::assertSame(1, $this->catalog->feed($source, 1));
        self::assertSame(2, $source->read);
    }

    public function testErrataArePublishedWithoutObserver(): void
    {
        $catalog = new Catalog($this->store, $this->clock);
        $erratum = new Erratum('er-1', 'p. 4', 'One round.', new DateTimeImmutable('2027-01-01'));

        $catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide')));
        $synchronized = $catalog->synchronize(new SourcedEntry('books', 'b-1', 'e-1', new Details('Tide'), errata: [$erratum]));
        $published = $catalog->publishErratum('e-1', new Erratum('er-2', 'p. 9', 'Two rounds.', new DateTimeImmutable('2027-02-01')));

        self::assertSame([$erratum], $synchronized->errata);
        self::assertSame(['er-1', 'er-2'], array_map(static fn (Erratum $held): string => $held->id, $published->errata));
    }

    public function testASourceFeedsEverythingByDefault(): void
    {
        $source = new ListedSource('books', [new SourcedEntry('books', 'b-1', 'e-1', new Details('One'))]);

        self::assertSame(1, $this->catalog->feed($source));
        self::assertSame(0, $this->catalog->feed(new ListedSource('books', []), 0));
    }

    public function testASourceSendsItsOwnEntriesOnly(): void
    {
        $this->expectException(CatalogRefused::class);
        $this->expectExceptionMessageIs('The source books sent an entry of the source lists.');
        $this->catalog->feed(new ListedSource('books', [new SourcedEntry('lists', 'b-1', 'e-1', new Details('One'))]));
    }

    public function testAnEntryIsHiddenAndShown(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));

        self::assertTrue($this->catalog->hide('e-1', 'admin')->hidden);
        self::assertSame(2, $this->catalog->hide('e-1')->version);
        self::assertFalse($this->catalog->show('e-1', 'admin')->hidden);
        self::assertSame(3, $this->catalog->show('e-1')->version);

        $history = $this->catalog->history('e-1');
        self::assertSame([ChangeKind::Shown, ChangeKind::Hidden, ChangeKind::Created], array_map(static fn (Change $change): ChangeKind => $change->kind, $history));
        self::assertSame('admin', $history[0]->by);
        self::assertSame('admin', $history[1]->by);
    }

    public function testAnIdentifierIsAdded(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));

        $entry = $this->catalog->addIdentifier('e-1', ExternalId::wikidata('Q9'), 'admin');
        self::assertSame(2, $entry->version);
        self::assertSame($entry, $this->catalog->addIdentifier('e-1', ExternalId::wikidata('Q9')));
        self::assertCount(2, $this->catalog->history('e-1'));
        self::assertSame('wikidata:Q9', $this->catalog->history('e-1')[0]->about);
        self::assertSame(ChangeKind::IdentifierAdded, $this->catalog->history('e-1')[0]->kind);
        self::assertSame('admin', $this->catalog->history('e-1')[0]->by);
    }

    public function testAnErratumIsPublished(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));
        $erratum = new Erratum('er-1', 'p. 2', 'Fixed.', new DateTimeImmutable());

        $entry = $this->catalog->publishErratum('e-1', $erratum, 'author');

        self::assertSame([$erratum], $entry->errata);
        self::assertSame(2, $entry->version);
        self::assertSame(['erratum e-1 er-1'], $this->observed->calls);
        self::assertSame(ChangeKind::ErratumPublished, $this->catalog->history('e-1')[0]->kind);
        self::assertSame('author', $this->catalog->history('e-1')[0]->by);
        self::assertSame('er-1', $this->catalog->history('e-1')[0]->about);
    }

    public function testEntriesAreLinked(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));
        $this->catalog->add('e-2', new Details('Tide: Depths', type: EntryType::Expansion));
        $this->catalog->link(new Link('e-2', LinkType::Expands, 'e-1', 1));
        $this->catalog->link(new Link('e-2', LinkType::Expands, 'e-1', 2));

        self::assertSame(2, $this->catalog->sheet('e-1')->links[0]->ordinal);
        self::assertCount(1, $this->catalog->sheet('e-2')->links);

        $this->catalog->unlink(new Link('e-2', LinkType::Expands, 'e-1'));
        self::assertSame([], $this->catalog->sheet('e-1')->links);
    }

    public function testBothEntriesOfALinkAreInTheCatalogue(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));

        try {
            $this->catalog->link(new Link('e-1', LinkType::Expands, 'e-9'));
            self::fail('A link to an unknown entry was kept.');
        } catch (EntryNotFound) {
        }

        $this->expectException(EntryNotFound::class);
        $this->catalog->link(new Link('e-9', LinkType::Expands, 'e-1'));
    }

    public function testAnEntryHasAtMostTwoHundredLinks(): void
    {
        $this->catalog->add('base', new Details('Tide'));

        for ($i = 1; $i <= Catalog::MAX_LINKS + 1; ++$i) {
            $this->catalog->add('x'.$i, new Details('Expansion '.$i));
        }

        for ($i = 1; $i <= Catalog::MAX_LINKS; ++$i) {
            $this->catalog->link(new Link('x'.$i, LinkType::Expands, 'base'));
        }

        $this->catalog->link(new Link('x1', LinkType::Expands, 'base', 7));
        self::assertCount(200, $this->store->links('base'));

        try {
            $this->catalog->link(new Link('x201', LinkType::Expands, 'base'));
            self::fail('A 201st link was kept.');
        } catch (CatalogRefused $catalogRefused) {
            self::assertSame('The entry base has 200 links.', $catalogRefused->getMessage());
        }

        $this->expectExceptionMessageIs('The entry base has 200 links.');
        $this->catalog->link(new Link('base', LinkType::Recommends, 'x201'));
    }

    public function testABarcodeIsGivenOnce(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));
        $barcode = new Gtin('4006381333931');

        $this->catalog->assignBarcode($barcode, 'e-1', 'admin');
        $this->catalog->assignBarcode($barcode, 'e-1', 'admin');

        self::assertSame('e-1', $this->catalog->identify($barcode)?->id);
        self::assertCount(2, $this->catalog->history('e-1'));
        self::assertSame(ChangeKind::BarcodeAssigned, $this->catalog->history('e-1')[0]->kind);
        self::assertSame('04006381333931', $this->catalog->history('e-1')[0]->about);
        self::assertSame('admin', $this->catalog->history('e-1')[0]->by);

        $this->expectException(EntryNotFound::class);
        $this->catalog->assignBarcode(new Gtin('036000291452'), 'e-9');
    }

    public function testAnUnknownBoxIsIdentifiedOnceThenLocally(): void
    {
        $resolver = new MappedResolver(['04006381333931' => ExternalId::rpgGeek(5), '00036000291452' => ExternalId::rpgGeek(6)]);
        $catalog = new Catalog($this->store, $this->clock, resolver: $resolver);
        $catalog->add('e-1', new Details('Tide'), [ExternalId::rpgGeek(5)]);

        $barcode = new Gtin('4006381333931');

        self::assertSame('e-1', $catalog->identify($barcode)?->id);
        self::assertSame('e-1', $catalog->identify($barcode)->id);
        self::assertSame(1, $resolver->asked);
        self::assertSame('e-1', $this->store->findByBarcode($barcode));
        self::assertSame(ChangeKind::BarcodeAssigned, $catalog->history('e-1')[0]->kind);
        self::assertNull($catalog->history('e-1')[0]->by);

        self::assertNull($catalog->identify(new Gtin('036000291452')));
        self::assertNull($catalog->identify(new Gtin('73513537')));
        self::assertSame(3, $resolver->asked);
        self::assertNull($this->store->findByBarcode(new Gtin('036000291452')));
    }

    public function testWithoutResolverAnUnknownBoxIsUnknown(): void
    {
        self::assertNull($this->catalog->identify(new Gtin('4006381333931')));
    }

    public function testASheetHoldsTheVotes(): void
    {
        $this->catalog->add('e-1', new Details('Tide'));
        $this->store->saveBallot(new Ballot('e-1', 'm-1', weight: 4));
        $this->store->saveBallot(new Ballot('e-2', 'm-1', weight: 1));

        $sheet = $this->catalog->sheet('e-1');

        self::assertSame('Tide', $sheet->details()->name);
        self::assertSame(400, $sheet->results->weight());
        self::assertSame(1, $sheet->results->voters);
    }
}
