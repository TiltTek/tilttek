<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ReferenceTables\Column;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\UnknownTable;
use Tilttek\ReferenceTables\Lookup;
use Tilttek\ReferenceTables\Range;
use Tilttek\ReferenceTables\ReferenceTable;
use Tilttek\ReferenceTables\Row;
use Tilttek\ReferenceTables\TableCatalogue;
use Tilttek\RpgSystems\Attribution\AttributionRenderer;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;

#[CoversClass(TableCatalogue::class)]
#[CoversClass(UnknownTable::class)]
final class TableCatalogueTest extends TestCase
{
    public function testGivesItsTablesByKeyAndInOrder(): void
    {
        $catalogue = new TableCatalogue(Tables::keyed(), Tables::ranged(['1-6' => 'A']));

        self::assertCount(2, $catalogue);
        self::assertTrue($catalogue->has('armor'));
        self::assertFalse($catalogue->has('weather'));
        self::assertSame('reaction', $catalogue->get('reaction')->key);
        self::assertSame(['armor', 'reaction'], array_map(static fn (ReferenceTable $table): string => $table->key, $catalogue->tables()));
    }

    public function testRefusesATableItDoesNotHold(): void
    {
        $this->expectException(UnknownTable::class);
        $this->expectExceptionMessageIsOrContains('The catalogue holds no table "weather".');

        new TableCatalogue()->get('weather');
    }

    public function testRefusesATableTwice(): void
    {
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains('The catalogue has the table "armor" twice.');

        new TableCatalogue(Tables::keyed(), Tables::ranged(['1' => 'A']), Tables::keyed());
    }

    public function testHoldsUpToItsLimit(): void
    {
        $table = Tables::keyed(['plate' => 5]);
        $tables = array_map(static fn (int $index): ReferenceTable => new ReferenceTable('t'.$index, $table->names, $table->provenance, $table->columns, $table->lookup, $table->rows), range(1, TableCatalogue::MAX_TABLES));
        $catalogue = new TableCatalogue(...$tables);

        self::assertCount(TableCatalogue::MAX_TABLES, $catalogue);
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains('A catalogue holds 2000 tables at most.');

        $catalogue->with(Tables::keyed());
    }

    public function testAddsTablesAfterItsOwn(): void
    {
        $catalogue = new TableCatalogue(Tables::keyed())->with(Tables::ranged(['1' => 'A']), Tables::ranged(['1' => 'B'], key: 'weather'));

        self::assertSame(['armor', 'reaction', 'weather'], array_map(static fn (ReferenceTable $table): string => $table->key, $catalogue->tables()));
    }

    public function testGivesTheTablesOfASystemAndOfACategory(): void
    {
        $catalogue = new TableCatalogue(
            Tables::ranged(['1' => 'A'], key: 'a', system: 'lanterns', category: 'social'),
            Tables::ranged(['1' => 'A'], key: 'b', system: 'other', category: 'social'),
            Tables::ranged(['1' => 'A'], key: 'c', system: 'lanterns', category: 'combat'),
            Tables::ranged(['1' => 'A'], key: 'd', system: 'lanterns'),
            Tables::ranged(['1' => 'A'], key: 'e', category: 'combat'),
        );
        $keys = static fn (array $tables): array => array_map(static fn (ReferenceTable $table): string => $table->key, $tables);

        self::assertSame(['a', 'c', 'd'], $keys($catalogue->tables('lanterns')));
        self::assertSame(['a', 'b'], $keys($catalogue->tables(category: 'social')));
        self::assertSame(['c'], $keys($catalogue->tables('lanterns', 'combat')));
        self::assertSame(['social', 'combat'], $catalogue->categories());
        self::assertSame(['social', 'combat'], $catalogue->categories('lanterns'));
        self::assertSame(['social'], $catalogue->categories('other'));
        self::assertSame([], $catalogue->categories('none'));
    }

    public function testGivesNumericCategoriesAsText(): void
    {
        $catalogue = new TableCatalogue(
            Tables::ranged(['1' => 'A'], key: 'a', category: '2'),
            Tables::ranged(['1' => 'A'], key: 'b', category: '2'),
        );

        self::assertSame(['2'], $catalogue->categories());
    }

    public function testGivesEachSourceOnceForTheAttribution(): void
    {
        $srd = new Provenance('System Reference Document 5.2', Licence::CcBy40, 'This work includes material from the SRD 5.2, CC BY 4.0.');
        $make = static fn (string $key, Provenance $source): ReferenceTable => new ReferenceTable($key, Tables::text('T'), $source, [new Column('a', Tables::text('A'))], Lookup::Range, [Row::ranged(Range::of(1), [])]);
        $catalogue = new TableCatalogue($make('a', $srd), $make('b', Tables::source('Own')), $make('c', new Provenance('System Reference Document 5.2', Licence::CcBy40, 'This work includes material from the SRD 5.2, CC BY 4.0.')));

        self::assertSame($srd, $catalogue->provenances()[0]);
        self::assertSame(['System Reference Document 5.2', 'Own'], array_map(static fn (Provenance $source): string => $source->title, $catalogue->provenances()));
        self::assertSame(['Own'], array_map(static fn (Provenance $source): string => $source->title, $catalogue->provenances(['b'])));
        self::assertSame(['System Reference Document 5.2'], array_map(static fn (Provenance $source): string => $source->title, $catalogue->provenances(['c', 'a'])));
        self::assertStringContainsString('SRD 5.2', new AttributionRenderer()->text($catalogue->provenances(['a', 'b'])));
        $this->expectException(UnknownTable::class);

        $catalogue->provenances(['z']);
    }
}
