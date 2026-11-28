<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Integration;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\GameCatalog\Ballot;
use Tilttek\GameCatalog\Catalog;
use Tilttek\GameCatalog\Corrections;
use Tilttek\GameCatalog\Details;
use Tilttek\GameCatalog\EntryType;
use Tilttek\GameCatalog\Erratum;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Field;
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\Gtin;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Import\CollectionImporter;
use Tilttek\GameCatalog\Import\CollectionItem;
use Tilttek\GameCatalog\Import\CollectionReader;
use Tilttek\GameCatalog\Import\Columns;
use Tilttek\GameCatalog\LanguageDependence;
use Tilttek\GameCatalog\Link;
use Tilttek\GameCatalog\LinkType;
use Tilttek\GameCatalog\PlayerCountChoice;
use Tilttek\GameCatalog\Polls;
use Tilttek\GameCatalog\Range;
use Tilttek\GameCatalog\SourcedEntry;
use Tilttek\GameCatalog\Store\InMemoryCatalogStore;
use Tilttek\GameCatalog\Tests\Unit\Fixture\ListedSource;
use Tilttek\GameCatalog\Tests\Unit\Fixture\MappedResolver;
use Tilttek\GameCatalog\Tests\Unit\Fixture\Observed;

/**
 * The services of the library on one store, as an application wires them: a source feeds the catalogue, a site adds a
 * scenario by hand and links it, members vote and correct, the source sends its entries again, a member imports the
 * file they exported, and a box is scanned twice.
 */
#[CoversClass(Catalog::class)]
#[CoversClass(Corrections::class)]
#[CoversClass(Polls::class)]
#[CoversClass(CollectionReader::class)]
#[CoversClass(CollectionImporter::class)]
final class CatalogLifecycleTest extends TestCase
{
    public function testAGameFromItsSourceToAMembersShelf(): void
    {
        $store = new InMemoryCatalogStore();
        $clock = new MockClock(new DateTimeImmutable('2027-04-01 09:00 UTC'));
        $observed = new Observed();
        $resolver = new MappedResolver(['09782070368228' => ExternalId::wikidata('Q100')]);
        $catalog = new Catalog($store, $clock, $observed, $resolver);
        $corrections = new Corrections($store, $clock, $observed);
        $polls = new Polls($store);

        $core = new Details('Lanterns', kind: GameKind::RolePlaying, system: 'lanterns', publishers: ['North Press'], players: new Range(3, 6), languages: ['fr']);
        $books = new ListedSource('books', [
            new SourcedEntry('books', 'core', 'lanterns', $core, [ExternalId::rpgGeek(100), ExternalId::wikidata('Q100')]),
        ]);
        self::assertSame(1, $catalog->feed($books));

        $catalog->add('lanterns-deep', new Details('The Deep', type: EntryType::Scenario, kind: GameKind::RolePlaying, system: 'lanterns', levels: new Range(1, 4)), [ExternalId::rpgGeek(101)], 'admin');
        $catalog->link(new Link('lanterns-deep', LinkType::Requires, 'lanterns'));

        $polls->vote(new Ballot('lanterns', 'm-1', [4 => 5, 6 => 2], [4 => PlayerCountChoice::Best, 6 => PlayerCountChoice::NotRecommended], 2, 12, LanguageDependence::Extensive));
        $polls->vote(new Ballot('lanterns', 'm-2', [4 => 4, 5 => 4], [4 => PlayerCountChoice::Best, 5 => PlayerCountChoice::Recommended], 3));

        $corrections->propose('c-1', 'lanterns', 'm-2', $core->merge(new Details('x', players: new Range(2, 6)), [Field::Players]));
        $corrections->accept('c-1', 'admin');

        $erratum = new Erratum('lanterns-e1', 'p. 88', 'The lantern lights two zones, not three.', new DateTimeImmutable('2027-04-02'));
        $books = new ListedSource('books', [
            new SourcedEntry('books', 'core', 'lanterns', $core->merge(new Details('Lanterns, second printing'), [Field::Name]), [ExternalId::rpgGeek(100)], [$erratum]),
        ]);
        $catalog->feed($books);

        $sheet = $catalog->sheet('lanterns');
        self::assertSame('Lanterns, second printing', $sheet->details()->name);
        self::assertSame(2, $sheet->details()->players?->min);
        self::assertSame(3, $sheet->entry->source->players?->min);
        self::assertSame([4, 5], $sheet->results->topPlayerCounts());
        self::assertSame([4], $sheet->results->bestByChoice());
        self::assertSame(250, $sheet->results->weight());
        self::assertSame([$erratum], $sheet->entry->errata);
        self::assertSame('lanterns-deep', $sheet->linksOf(LinkType::Requires)[0]->other('lanterns'));
        self::assertSame('https://rpggeek.com/rpgitem/100', $sheet->entry->identifier(IdentifierScheme::RpgGeek)?->link());
        self::assertSame(['proposed lanterns c-1', 'decided c-1 m-2 accepted', 'erratum lanterns lanterns-e1'], $observed->calls);

        $export = "objectname,objectid,own,wishlist,wishlistpriority,numplays\nLanterns,100,1,0,,7\nThe Deep,101,0,1,1,\nUnknown,999,1,0,,\n";
        $file = new CollectionReader()->read($export, Columns::geekExport(IdentifierScheme::RpgGeek));
        $import = new CollectionImporter($store)->import($file);
        self::assertSame(['lanterns', 'lanterns-deep'], array_map(static fn (CollectionItem $item): string => $item->entry, $import->items));
        self::assertSame(7, $import->items[0]->plays);
        self::assertSame(1, $import->items[1]->priority);
        self::assertSame('rpggeek:999', $import->unmatched[0]->key());

        $box = new Gtin('978-2-07-036822-8');
        self::assertSame('lanterns', $catalog->identify($box)?->id);
        self::assertSame('lanterns', $catalog->identify(new Gtin('9782070368228'))?->id);
        self::assertSame(1, $resolver->asked);
    }
}
