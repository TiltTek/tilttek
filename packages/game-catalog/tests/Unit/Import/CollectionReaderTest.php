<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Import;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameCatalog\CollectionStatus;
use Tilttek\GameCatalog\Exception\InvalidCollectionFile;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Import\CollectionFile;
use Tilttek\GameCatalog\Import\CollectionReader;
use Tilttek\GameCatalog\Import\CollectionRow;
use Tilttek\GameCatalog\Import\Columns;
use Tilttek\GameCatalog\Import\Limits;
use Tilttek\GameCatalog\Import\RowError;

#[CoversClass(CollectionReader::class)]
#[CoversClass(Columns::class)]
#[CoversClass(Limits::class)]
#[CoversClass(CollectionFile::class)]
#[CoversClass(CollectionRow::class)]
#[CoversClass(RowError::class)]
#[CoversClass(InvalidCollectionFile::class)]
final class CollectionReaderTest extends TestCase
{
    private const string EXPORT = <<<'CSV'
        objectname,objectid,rating,numplays,own,fortrade,want,wanttobuy,wanttoplay,prevowned,preordered,wishlist,wishlistpriority,comment
        "Lanterns, of the Deep",101,8,3,1,0,0,0,1,0,0,0,,"Great ""campaign"""
        Tide,202,,0,0,0,1,1,0,1,1,1,2,
        CSV;

    public function testAnExportFromAGeekIsRead(): void
    {
        $file = new CollectionReader()->read(self::EXPORT, Columns::geekExport(IdentifierScheme::RpgGeek));

        self::assertSame([], $file->errors);
        self::assertSame(0, $file->moreErrors);
        self::assertCount(2, $file->rows);
        [$first, $second] = $file->rows;
        self::assertSame(2, $first->line);
        self::assertTrue($first->identifier->equals(ExternalId::rpgGeek(101)));
        self::assertSame([CollectionStatus::Owned, CollectionStatus::WantToPlay], $first->statuses);
        self::assertNull($first->priority);
        self::assertSame(3, $first->plays);
        self::assertSame(3, $second->line);
        self::assertSame([CollectionStatus::PreviouslyOwned, CollectionStatus::Preordered, CollectionStatus::WantInTrade, CollectionStatus::WantToBuy, CollectionStatus::Wishlist], $second->statuses);
        self::assertSame(2, $second->priority);
        self::assertSame(0, $second->plays);
    }

    public function testTheColumnsOfAGeekExport(): void
    {
        $columns = Columns::geekExport(IdentifierScheme::BoardGameGeek);

        self::assertSame('objectid', $columns->identifier);
        self::assertSame(IdentifierScheme::BoardGameGeek, $columns->scheme);
        self::assertNull($columns->schemeColumn);
        self::assertSame('wishlistpriority', $columns->priority);
        self::assertSame('numplays', $columns->plays);
        self::assertSame(',', $columns->delimiter);
        self::assertSame(['own', 'prevowned', 'preordered', 'fortrade', 'want', 'wanttobuy', 'wanttoplay', 'wishlist'], array_keys($columns->statuses));
        self::assertSame(CollectionStatus::cases(), array_values($columns->statuses));
    }

    public function testTheHeadersAreMatchedWithoutCaseOrSpaces(): void
    {
        $columns = new Columns(' Game ', schemeColumn: 'DB ', statuses: [' Owned ' => CollectionStatus::Owned], priority: 'Priority', plays: 'Plays', delimiter: ';');

        self::assertSame('game', $columns->identifier);
        self::assertSame('db', $columns->schemeColumn);
        self::assertSame(['owned' => CollectionStatus::Owned], $columns->statuses);
        self::assertSame('priority', $columns->priority);
        self::assertSame('plays', $columns->plays);
        self::assertNull($columns->scheme);

        $file = new CollectionReader()->read("\u{FEFF}GAME ; db;OWNED;priority;plays;game\r\n Q42 ;WikiData;Yes;5;100000;9\r\n\r\n7;rpggeek;x;;;\n", $columns);

        self::assertSame([], $file->errors);
        self::assertCount(2, $file->rows);
        self::assertTrue($file->rows[0]->identifier->equals(ExternalId::wikidata('Q42')));
        self::assertSame([CollectionStatus::Owned], $file->rows[0]->statuses);
        self::assertSame(5, $file->rows[0]->priority);
        self::assertSame(100_000, $file->rows[0]->plays);
        self::assertTrue($file->rows[1]->identifier->equals(ExternalId::rpgGeek(7)));
        self::assertSame(4, $file->rows[1]->line);
        self::assertNull($file->rows[1]->plays);
    }

    public function testColumnsNotInTheFileAreLeftOut(): void
    {
        $file = new CollectionReader()->read("objectid\n12\n", Columns::geekExport(IdentifierScheme::RpgGeek));

        self::assertSame([], $file->rows[0]->statuses);
        self::assertNull($file->rows[0]->priority);
        self::assertNull($file->rows[0]->plays);
    }

    public function testTheStatusesAcceptTheirSpellings(): void
    {
        $columns = new Columns('id', IdentifierScheme::RpgGeek, statuses: ['a' => CollectionStatus::Owned, 'b' => CollectionStatus::Wishlist, 'c' => CollectionStatus::ForTrade, 'd' => CollectionStatus::WantToPlay]);
        $file = new CollectionReader()->read("id,a,b,c,d\n1,1,yes,TRUE,X\n2,0,no,FALSE,\n", $columns);

        self::assertSame([CollectionStatus::Owned, CollectionStatus::ForTrade, CollectionStatus::WantToPlay, CollectionStatus::Wishlist], $file->rows[0]->statuses);
        self::assertSame([], $file->rows[1]->statuses);
    }

    /**
     * @return iterable<string, array{string, non-empty-string}>
     */
    public static function badRows(): iterable
    {
        yield 'no identifier' => ['1,,1,,', 'is not an identifier of rpggeek.'];
        yield 'a bad identifier' => ['2,Q3,1,,', '"Q3" is not an identifier of rpggeek.'];
        yield 'a bad status' => ['3,4,2,,', 'The status owned is 1 or 0, yes or no, true or false, x or empty.'];
        yield 'a priority of 0' => ['4,5,1,0,', 'The priority of a wish is a whole number from 1 to 5.'];
        yield 'a priority of 6' => ['5,6,1,6,', 'The priority of a wish is a whole number from 1 to 5.'];
        yield 'a priority in words' => ['6,7,1,high,', 'The priority of a wish is a whole number from 1 to 5.'];
        yield 'a negative play count' => ['7,8,1,,-1', 'The games played is a whole number from 0 to 100000.'];
        yield 'too many plays' => ['8,9,1,,100001', 'The games played is a whole number from 0 to 100000.'];
        yield 'seven digits of plays' => ['9,10,1,,0000001', 'The games played is a whole number from 0 to 100000.'];
        yield 'a decimal play count' => ['10,11,1,,2.5', 'The games played is a whole number from 0 to 100000.'];
    }

    /**
     * @param non-empty-string $message
     */
    #[DataProvider('badRows')]
    public function testARowThatCannotBeReadIsReportedWithItsLine(string $row, string $message): void
    {
        $columns = new Columns('id', IdentifierScheme::RpgGeek, statuses: ['own' => CollectionStatus::Owned], priority: 'priority', plays: 'plays');
        $file = new CollectionReader()->read("n,id,own,priority,plays\n0,99,1,,\n".$row."\n0,98,0,,\n", $columns);

        self::assertCount(2, $file->rows);
        self::assertSame(['rpggeek:99', 'rpggeek:98'], array_map(static fn (CollectionRow $row): string => $row->identifier->key(), $file->rows));
        self::assertCount(1, $file->errors);
        self::assertSame(3, $file->errors[0]->line);
        self::assertStringEndsWith($message, $file->errors[0]->message);
    }

    public function testTheLastRowNeedsNoLineBreak(): void
    {
        $file = new CollectionReader()->read("id\n5", new Columns('id', IdentifierScheme::RpgGeek));

        self::assertSame(['rpggeek:5'], array_map(static fn (CollectionRow $row): string => $row->identifier->key(), $file->rows));
        self::assertSame([], $file->errors);
    }

    public function testAGameIsReadOnce(): void
    {
        $file = new CollectionReader()->read("id\n5\n6\n5\n", new Columns('id', IdentifierScheme::RpgGeek));

        self::assertCount(2, $file->rows);
        self::assertSame([4], array_map(static fn (RowError $error): int => $error->line, $file->errors));
        self::assertSame('The game rpggeek:5 is on row 2 already.', $file->errors[0]->message);
    }

    public function testTheDatabaseOfARowIsOneOfThree(): void
    {
        $file = new CollectionReader()->read("id,db\n5,bgg\n", new Columns('id', schemeColumn: 'db'));

        self::assertSame([], $file->rows);
        self::assertSame('The database of the game is boardgamegeek, rpggeek or wikidata.', $file->errors[0]->message);
    }

    public function testTheErrorsReportedAreLimited(): void
    {
        $file = new CollectionReader()->read("id\nx\ny\nz\n7\n", new Columns('id', IdentifierScheme::RpgGeek), new Limits(errors: 2));

        self::assertCount(1, $file->rows);
        self::assertSame([2, 3], array_map(static fn (RowError $error): int => $error->line, $file->errors));
        self::assertSame(1, $file->moreErrors);
    }

    public function testARowOrACellOverItsLimitIsLeftOut(): void
    {
        $limits = new Limits(columns: 3, cellBytes: 4);
        $file = new CollectionReader()->read("id,a,b\n1\n1234,x,y,z\n12345\n55,abcd\n7,a,b\n", new Columns('id', IdentifierScheme::RpgGeek), $limits);

        self::assertSame(['rpggeek:1', 'rpggeek:55', 'rpggeek:7'], array_map(static fn (CollectionRow $row): string => $row->identifier->key(), $file->rows));
        self::assertSame([[3, 'A row has at most 3 cells.'], [4, 'A cell is at most 4 bytes.']], array_map(static fn (RowError $error): array => [$error->line, $error->message], $file->errors));
    }

    public function testTheLimitsAreReachedExactly(): void
    {
        $columns = new Columns('id', IdentifierScheme::RpgGeek);
        $contents = "id\n1\n2\n";

        self::assertCount(2, new CollectionReader()->read($contents, $columns, new Limits(bytes: \strlen($contents), rows: 2, columns: 1))->rows);
    }

    /**
     * @return iterable<string, array{string, Limits, string}>
     */
    public static function badFiles(): iterable
    {
        yield 'too big' => ["id\n1\n", new Limits(bytes: 4), 'A collection file is at most 4 bytes.'];
        yield 'too many rows' => ["id\n1\n2\n3\n", new Limits(rows: 2), 'A collection file has at most 2 rows.'];
        yield 'too many columns' => ["id,a,b\n1\n", new Limits(columns: 2), 'A collection file has at most 2 columns.'];
        yield 'empty' => ['', new Limits(), 'A collection file starts with a header row.'];
        yield 'a blank first line' => ["\nid\n1\n", new Limits(), 'A collection file starts with a header row.'];
        yield 'not UTF-8' => ["id\n\xFF\n", new Limits(), 'A collection file is UTF-8.'];
        yield 'no identifier column' => ["name\nTide\n", new Limits(), 'The collection file has no column "id".'];
    }

    #[DataProvider('badFiles')]
    public function testAFileThatCannotBeReadIsRefused(string $contents, Limits $limits, string $message): void
    {
        $this->expectException(InvalidCollectionFile::class);
        $this->expectExceptionMessageIs($message);
        new CollectionReader()->read($contents, new Columns('id', IdentifierScheme::RpgGeek), $limits);
    }

    public function testTheColumnOfTheDatabaseIsRequired(): void
    {
        $this->expectExceptionMessageIs('The collection file has no column "db".');
        new CollectionReader()->read("id\n1\n", new Columns('id', schemeColumn: 'db'));
    }

    public function testTheDatabaseIsGivenOnce(): void
    {
        try {
            new Columns('id');
            self::fail('Columns without a database were made.');
        } catch (InvalidCollectionFile $invalidCollectionFile) {
            self::assertSame('The database of the identifiers is given once: for the whole file, or by a column.', $invalidCollectionFile->getMessage());
        }

        $this->expectExceptionMessageIs('The database of the identifiers is given once: for the whole file, or by a column.');
        new Columns('id', IdentifierScheme::RpgGeek, 'db');
    }

    public function testAFileWithoutRowsHasNoError(): void
    {
        $file = new CollectionFile([]);

        self::assertSame([], $file->rows);
        self::assertSame([], $file->errors);
        self::assertSame(0, $file->moreErrors);
    }

    public function testTheDelimiterIsOneOfFour(): void
    {
        foreach ([',', ';', "\t", '|'] as $delimiter) {
            self::assertSame($delimiter, new Columns('id', IdentifierScheme::RpgGeek, delimiter: $delimiter)->delimiter);
        }

        $this->expectExceptionMessageIs('The delimiter of a collection file is ",", ";", a tab or "|".');
        new Columns('id', IdentifierScheme::RpgGeek, delimiter: ':');
    }

    public function testTheLimitsAreOneOrMore(): void
    {
        $limits = new Limits();
        self::assertSame([2_097_152, 20_000, 100, 4_096, 100], [$limits->bytes, $limits->rows, $limits->columns, $limits->cellBytes, $limits->errors]);
        self::assertSame(1, new Limits(1, 1, 1, 1, 1)->errors);

        foreach ([[0, 1, 1, 1, 1], [1, 0, 1, 1, 1], [1, 1, 0, 1, 1], [1, 1, 1, 0, 1], [1, 1, 1, 1, 0]] as $bounds) {
            try {
                new Limits(...$bounds);
                self::fail('A limit of 0 was taken.');
            } catch (InvalidCollectionFile $refused) {
                self::assertSame('Each limit of a collection file is 1 or more.', $refused->getMessage());
            }
        }
    }
}
