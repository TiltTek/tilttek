<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit\Search;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Format\TableReader;
use Tilttek\ReferenceTables\Internal\Fold;
use Tilttek\ReferenceTables\Search\SearchHit;
use Tilttek\ReferenceTables\Search\TableSearch;
use Tilttek\ReferenceTables\TableCatalogue;
use Tilttek\ReferenceTables\Tests\Unit\Tables;

#[CoversClass(TableSearch::class)]
#[CoversClass(SearchHit::class)]
#[CoversClass(Fold::class)]
final class TableSearchTest extends TestCase
{
    private TableCatalogue $catalogue;

    protected function setUp(): void
    {
        $this->catalogue = new TableReader()->load(\dirname(__DIR__, 3).'/examples/lanterns.yaml');
    }

    public function testFindsANameInALocale(): void
    {
        $hits = new TableSearch()->search($this->catalogue, 'réaction', 'fr');

        self::assertEquals([new SearchHit('reaction', null, null, 'Réaction')], $hits);
    }

    public function testFindsADescriptionACellAndARowKey(): void
    {
        $search = new TableSearch();

        self::assertEquals([new SearchHit('reaction', null, null, 'How a stranger first meets the party.')], $search->search($this->catalogue, 'STRANGER'));
        self::assertEquals([new SearchHit('reaction', 4, 'attitude', 'Serviable')], $search->search($this->catalogue, 'servi', 'fr'));
        self::assertEquals([new SearchHit('armor', 2, null, 'plate'), new SearchHit('armor', 2, 'name', 'Plate')], $search->search($this->catalogue, ' plate '));
    }

    public function testIgnoresCaseAndAccents(): void
    {
        $search = new TableSearch();

        self::assertEquals([new SearchHit('armor', 2, 'name', 'Plates')], $search->search($this->catalogue, 'PLATES', 'fr'));
        self::assertEquals([new SearchHit('wandering', 3, 'encounter', 'Le fantôme à la lanterne')], $search->search($this->catalogue, 'FANTOME A', 'fr'));
        self::assertEquals([new SearchHit('reaction', 1, 'attitude', 'Méfiant')], $search->search($this->catalogue, 'méfiant', 'fr'));
        self::assertSame('aaaaaaaeceeeeiiiinoooooooeuuuuyyss', Fold::text('ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÑÒÓÔÕÖØŒÙÚÛÜÝŸß'));
    }

    public function testReadsTextCellsOnly(): void
    {
        self::assertSame([], new TableSearch()->search($this->catalogue, '3d6'));
        self::assertSame([], new TableSearch()->search($this->catalogue, '400'));
    }

    public function testSearchesOneSystem(): void
    {
        $catalogue = $this->catalogue->with(Tables::ranged(['1' => 'Plate armour'], key: 'other', system: 'other'));
        $search = new TableSearch();

        self::assertCount(3, $search->search($catalogue, 'plate'));
        self::assertSame(['armor', 'armor'], array_map(static fn (SearchHit $hit): string => $hit->table, $search->search($catalogue, 'plate', system: 'lanterns')));
        self::assertSame(['other'], array_map(static fn (SearchHit $hit): string => $hit->table, $search->search($catalogue, 'plate', system: 'other')));
    }

    public function testStopsAtTheLimit(): void
    {
        $search = new TableSearch();
        $all = $search->search($this->catalogue, 'e', limit: TableSearch::MAX_HITS);

        self::assertGreaterThan(3, \count($all));
        self::assertEquals(\array_slice($all, 0, 3), $search->search($this->catalogue, 'e', limit: 3));
        self::assertEquals(\array_slice($all, 0, 1), $search->search($this->catalogue, 'e', limit: 1));
        self::assertCount(20, $search->search($this->catalogue, 'e'));
    }

    public function testGivesTwentyHitsByDefault(): void
    {
        $rows = [];

        for ($row = 1; $row <= 25; ++$row) {
            $rows[(string) $row] = 'Lantern '.$row;
        }

        $catalogue = new TableCatalogue(Tables::ranged($rows));

        self::assertCount(20, new TableSearch()->search($catalogue, 'lantern'));
        self::assertCount(21, new TableSearch()->search($catalogue, 'lantern', limit: 21));
    }

    public function testAcceptsTheLongestQuery(): void
    {
        self::assertSame([], new TableSearch()->search($this->catalogue, str_repeat('é', TableSearch::MAX_QUERY)));
    }

    /**
     * @return iterable<string, array{string, int, string}>
     */
    public static function refused(): iterable
    {
        yield 'an empty query' => [' ', 20, 'A query is 1 to 100 characters of UTF-8.'];
        yield 'a query too long' => [str_repeat('é', 101), 20, 'A query is 1 to 100 characters of UTF-8.'];
        yield 'a query not UTF-8' => ["\xFF", 20, 'A query is 1 to 100 characters of UTF-8.'];
        yield 'no hit' => ['a', 0, 'A search gives 1 to 100 hits.'];
        yield 'too many hits' => ['a', 101, 'A search gives 1 to 100 hits.'];
    }

    #[DataProvider('refused')]
    public function testRefusesItsBounds(string $query, int $limit, string $message): void
    {
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains($message);

        new TableSearch()->search($this->catalogue, $query, limit: $limit);
    }
}
