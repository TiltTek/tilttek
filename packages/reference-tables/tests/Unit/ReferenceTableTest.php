<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\ReferenceTables\Cell;
use Tilttek\ReferenceTables\Column;
use Tilttek\ReferenceTables\ColumnKind;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\LookupRefused;
use Tilttek\ReferenceTables\Exception\UnknownRow;
use Tilttek\ReferenceTables\Exception\UnknownTable;
use Tilttek\ReferenceTables\Internal\Check;
use Tilttek\ReferenceTables\Lookup;
use Tilttek\ReferenceTables\Range;
use Tilttek\ReferenceTables\ReferenceTable;
use Tilttek\ReferenceTables\Row;
use Tilttek\RpgSystems\Translations;

#[CoversClass(ReferenceTable::class)]
#[CoversClass(Column::class)]
#[CoversClass(Cell::class)]
#[CoversClass(Row::class)]
#[CoversClass(Check::class)]
#[CoversClass(InvalidTable::class)]
#[CoversClass(LookupRefused::class)]
#[CoversClass(UnknownRow::class)]
#[CoversClass(UnknownTable::class)]
final class ReferenceTableTest extends TestCase
{
    public function testFindsTheRowOfAValueByItsRange(): void
    {
        $table = Tables::ranged(['≤2' => 'Hostile', '3-5' => 'Wary', '6-8' => 'Uncertain', '9-11' => 'Friendly', '12+' => 'Helpful']);

        $found = array_map(static fn (int $value): string => $table->lookup($value)->display('result'), [-40, 2, 3, 5, 6, 8, 9, 11, 12, 99]);

        self::assertSame(['Hostile', 'Hostile', 'Wary', 'Wary', 'Uncertain', 'Uncertain', 'Friendly', 'Friendly', 'Helpful', 'Helpful'], $found);
    }

    public function testFindsTheRowWhateverTheOrderTheRowsAreGivenIn(): void
    {
        $table = Tables::ranged(['9-12' => 'C', '1-4' => 'A', '5-8' => 'B', '13-16' => 'D', '17-20' => 'E']);

        self::assertSame(['A', 'B', 'C', 'D', 'E'], array_map(static fn (int $value): string => $table->lookup($value)->display('result'), [1, 8, 9, 16, 17]));
        self::assertSame([1, 2, 0, 3, 4], array_map($table->position(...), [4, 5, 12, 13, 20]));
        self::assertSame('9–12', $table->rows[0]->label());
    }

    public function testGivesNoPositionOutsideItsRanges(): void
    {
        $table = Tables::ranged(['1-4' => 'A', '7-8' => 'B', '10' => 'C']);

        self::assertNull($table->position(0));
        self::assertNull($table->position(5));
        self::assertNull($table->position(6));
        self::assertNull($table->position(9));
        self::assertNull($table->position(11));
        self::assertSame(2, $table->position(10));
    }

    public function testRefusesAValueNoRangeCovers(): void
    {
        $table = Tables::ranged(['1-4' => 'A', '7-8' => 'B']);

        $this->expectException(UnknownRow::class);
        $this->expectExceptionMessageIsOrContains('No row of the table "reaction" covers 5.');

        $table->lookup(5);
    }

    public function testGivesItsBoundsAndItsGaps(): void
    {
        $table = Tables::ranged(['10-12' => 'D', '1-2' => 'A', '4' => 'B', '5-7' => 'C', '9' => 'C2']);

        self::assertSame([1, 12], [$table->bounds()->min, $table->bounds()->max]);
        self::assertSame(['3', '8'], array_map(static fn (Range $gap): string => $gap->toString(), $table->gaps()));
        self::assertSame([], Tables::ranged(['1-3' => 'A', '4-6' => 'B'])->gaps());
        self::assertSame(['2–5'], array_map(strval(...), Tables::ranged(['1' => 'A', '6' => 'B'])->gaps()));
    }

    public function testFindsEveryValueOfALongTable(): void
    {
        $rows = [];

        for ($row = 0; $row < 9; ++$row) {
            $rows[($row * 3 + 1).'-'.($row * 3 + 2)] = 'R'.$row;
        }

        $table = Tables::ranged($rows);

        for ($row = 0; $row < 9; ++$row) {
            self::assertSame($row, $table->position($row * 3 + 1));
            self::assertSame($row, $table->position($row * 3 + 2));
            self::assertNull($table->position($row * 3 + 3));
        }

        self::assertNull($table->position(0));
        self::assertNull($table->position(100));
    }

    public function testNamesTheOverlappingRowsInTheOrderOfTheirRanges(): void
    {
        foreach ([['1-10', '5-6'], ['5-6', '1-10']] as $order) {
            try {
                Tables::ranged([$order[0] => 'X', $order[1] => 'Y']);
                self::fail('Overlapping rows are refused.');
            } catch (InvalidTable $invalidTable) {
                self::assertSame('The rows "1–10" and "5–6" of the table "reaction" overlap.', $invalidTable->getMessage());
            }
        }
    }

    public function testFindsTheRowOfAKey(): void
    {
        $table = Tables::keyed();

        self::assertSame(5, $table->row('plate')->cell('defence')->toInt());
        self::assertSame(1, $table->row('padded')->cell('defence')->toInt());
        self::assertTrue($table->hasRow('plate'));
        self::assertFalse($table->hasRow('chain'));
        self::assertSame('plate', $table->row('plate')->label());
    }

    public function testRefusesAKeyItDoesNotHold(): void
    {
        $this->expectException(UnknownRow::class);
        $this->expectExceptionMessageIsOrContains('The table "armor" has no row "chain".');

        Tables::keyed()->row('chain');
    }

    public function testRefusesAKeyAskedOfATableLookedUpByRange(): void
    {
        $table = Tables::ranged(['1-6' => 'A']);

        self::assertFalse($table->hasRow('1-6'));
        $this->expectException(LookupRefused::class);
        $this->expectExceptionMessageIsOrContains('The table "reaction" is looked up by range, not by key.');

        $table->row('1-6');
    }

    public function testRefusesAValueAskedOfATableLookedUpByKey(): void
    {
        $this->expectException(LookupRefused::class);
        $this->expectExceptionMessageIsOrContains('The table "armor" is looked up by key, not by range.');

        Tables::keyed()->lookup(3);
    }

    public function testRefusesTheBoundsOfATableLookedUpByKey(): void
    {
        $this->expectExceptionMessageIsOrContains('The table "armor" is looked up by key, not by range.');

        Tables::keyed()->bounds();
    }

    public function testRefusesTheGapsOfATableLookedUpByKey(): void
    {
        $this->expectException(LookupRefused::class);

        Tables::keyed()->gaps();
    }

    public function testGivesItsColumns(): void
    {
        $table = Tables::keyed();

        self::assertTrue($table->hasColumn('defence'));
        self::assertFalse($table->hasColumn('cost'));
        self::assertSame('Defence', $table->column('defence')->name('fr'));
        self::assertSame(ColumnKind::Number, $table->column('defence')->kind);
        $this->expectException(UnknownTable::class);
        $this->expectExceptionMessageIsOrContains('The table "armor" has no column "cost".');

        $table->column('cost');
    }

    public function testGivesTheColumnAtItsPosition(): void
    {
        $table = new ReferenceTable('t', Tables::text('T'), Tables::source(), [new Column('a', Tables::text('A')), new Column('b', Tables::text('B'))], Lookup::Key, [Row::keyed('r', [])]);

        self::assertSame('b', $table->column('b')->key);
        self::assertSame('a', $table->column('a')->key);
    }

    public function testKeepsItsDieInTheCanonicalNotation(): void
    {
        $table = Tables::ranged(['1-100' => 'A'], 'd%');

        self::assertSame('1d100', $table->die);
        self::assertTrue($table->isRollable());
        self::assertTrue($table->isPercentile());
        self::assertSame('1d100', $table->pool()->canonical());
        self::assertFalse(Tables::ranged(['1-20' => 'A'], 'd20')->isPercentile());
        self::assertSame('2d20kh1', Tables::ranged(['1-20' => 'A'], 'd20 adv')->die);
    }

    public function testIsNotRolledWithoutADie(): void
    {
        $table = Tables::ranged(['1-4' => 'A'], null);

        self::assertFalse($table->isRollable());
        self::assertFalse($table->isPercentile());
        $this->expectException(LookupRefused::class);
        $this->expectExceptionMessageIsOrContains('The table "reaction" has no die to roll.');

        $table->pool();
    }

    public function testKeepsWhereItComesFrom(): void
    {
        $table = new ReferenceTable('reaction', Tables::text('Reaction', 'Réaction'), Tables::source('Lanterns'), [new Column('result', Tables::text('Result'))], Lookup::Range, [Row::ranged(Range::of(1), [])], '1d6', 'lanterns', 'social', 'p. 12', Tables::text('How strangers react.'));

        self::assertSame('Réaction', $table->name('fr_CA'));
        self::assertSame('Reaction', $table->name());
        self::assertSame('Lanterns', $table->provenance->title);
        self::assertSame(['lanterns', 'social', 'p. 12'], [$table->system, $table->category, $table->page]);
        self::assertSame('How strangers react.', $table->description?->in('en'));
        self::assertSame(Lookup::Range, $table->lookup);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedTables(): iterable
    {
        $column = [new Column('result', Tables::text('Result'))];
        $text = Tables::text('A');
        $one = [Row::ranged(Range::of(1), [])];

        yield 'a key that is not one' => [static fn (): ReferenceTable => new ReferenceTable('Reaction', $text, Tables::source(), $column, Lookup::Range, $one), 'The key of a table "Reaction" must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them.'];
        yield 'no name' => [static fn (): ReferenceTable => new ReferenceTable('t', new Translations(), Tables::source(), $column, Lookup::Range, $one), 'The table "t" has no name.'];
        yield 'a system that is not a key' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, system: 'Lanterns'), 'The key of the system of the table "t" "Lanterns" must be'];
        yield 'a category that is not a key' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, category: 'A B'), 'The key of the category of the table "t" "A B" must be'];
        yield 'an empty page' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, page: ' '), 'The page of the table "t" must be one line of 1 to 200 characters.'];
        yield 'a page of two lines' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, page: "p.\n12"), 'The page of the table "t" must be one line'];
        yield 'a page too long' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, page: str_repeat('é', 201)), 'must be one line of 1 to 200 characters.'];
        yield 'a page not UTF-8' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, page: "p. \xFF"), 'must be one line'];
        yield 'a page with a delete' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, page: "p.\x7F"), 'must be one line'];
        yield 'no column' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), [], Lookup::Range, $one), 'The table "t" has 1 to 32 columns.'];
        yield 'too many columns' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), array_map(static fn (int $index): Column => new Column('c'.$index, $text), range(1, 33)), Lookup::Range, $one), 'The table "t" has 1 to 32 columns.'];
        yield 'a column twice' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), [...$column, ...$column], Lookup::Range, $one), 'The table "t" has the column "result" twice.'];
        yield 'no row' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, []), 'The table "t" has 1 to 1000 rows.'];
        yield 'too many rows' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, array_map(static fn (int $value): Row => Row::ranged(Range::of($value), []), range(1, 1001))), 'The table "t" has 1 to 1000 rows.'];
        yield 'a keyed row in a table by range' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, [Row::keyed('a', [])]), 'The row "a" of the table "t" needs a range: the table is looked up by range.'];
        yield 'a ranged row in a table by key' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Key, $one), 'The row "1" of the table "t" needs a key: the table is looked up by key.'];
        yield 'a cell of no column' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, [Row::ranged(Range::of(1), ['other' => Cell::number(1)])]), 'The row "1" of the table "t" has a cell in the column "other", which the table does not have.'];
        yield 'a cell of another kind' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, [Row::ranged(Range::of(1), ['result' => Cell::number(1)])]), 'The cell "result" of the row "1" of the table "t" holds a number; the column holds a text.'];
        yield 'a row twice' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Key, [Row::keyed('a', []), Row::keyed('b', []), Row::keyed('a', [])]), 'The table "t" has the row "a" twice.'];
        yield 'overlapping rows' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, [Row::ranged(new Range(1, 5), []), Row::ranged(new Range(8, 9), []), Row::ranged(new Range(5, 6), [])]), 'The rows "1–5" and "5–6" of the table "t" overlap.'];
        yield 'a die on a table by key' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Key, [Row::keyed('a', [])], '1d6'), 'The table "t" is looked up by key: only a table looked up by range is rolled.'];
        yield 'a die that cannot be read' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, '1d'), 'The die "1d" of the table "t" cannot be read:'];
        yield 'a die of two instructions' => [static fn (): ReferenceTable => new ReferenceTable('t', $text, Tables::source(), $column, Lookup::Range, $one, '1d6, 1d8'), 'The die "1d6, 1d8" of the table "t" must be one instruction.'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedTables')]
    public function testRefusesATable(callable $build, string $message): void
    {
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testAcceptsItsLimits(): void
    {
        $text = Tables::text('A');
        $columns = array_map(static fn (int $index): Column => new Column('c'.$index, $text), range(1, 32));
        $rows = array_map(static fn (int $value): Row => Row::ranged(Range::of($value), []), range(1, 1000));
        $table = new ReferenceTable('t', $text, Tables::source(), $columns, Lookup::Range, $rows, page: str_repeat('é', 200));

        self::assertCount(32, $table->columns);
        self::assertCount(1000, $table->rows);
        self::assertSame(999, $table->position(1000));
        self::assertSame(0, $table->position(1));
        self::assertSame(499, $table->position(500));
    }

    public function testKeepsItsRowsAndColumnsAsAList(): void
    {
        $table = new ReferenceTable('t', Tables::text('T'), Tables::source(), ['x' => new Column('a', Tables::text('A'))], Lookup::Key, ['y' => Row::keyed('r', [])]);

        self::assertSame([0], array_keys($table->columns));
        self::assertSame([0], array_keys($table->rows));
        self::assertSame('r', $table->row('r')->key);
    }

    public function testAColumnHasAHeadingAndAKind(): void
    {
        $column = new Column('armor-class', Tables::text('Armor class', 'Classe d’armure'));

        self::assertSame(ColumnKind::Text, $column->kind);
        self::assertSame('Classe d’armure', $column->name('fr'));
        self::assertSame('Armor class', $column->name());
    }

    /**
     * @return iterable<string, array{string, Translations, string}>
     */
    public static function refusedColumns(): iterable
    {
        yield 'a key that is not one' => ['Armor', Tables::text('A'), 'The key of a column "Armor" must be'];
        yield 'a key starting with a digit' => ['5th', Tables::text('A'), 'The key of the column "5th" must start with a letter.'];
        yield 'the name of the row key' => ['key', Tables::text('A'), 'A column cannot be named "key": the name is kept for the rows.'];
        yield 'the name of the row range' => ['range', Tables::text('A'), 'A column cannot be named "range"'];
        yield 'no heading' => ['a', new Translations(), 'The column "a" has no heading.'];
    }

    #[DataProvider('refusedColumns')]
    public function testRefusesAColumn(string $key, Translations $names, string $message): void
    {
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Column($key, $names);
    }

    public function testACellHoldsATextANumberOrDice(): void
    {
        $text = Cell::text(Tables::text('Hostile', 'Hostile'));
        $number = Cell::number(-12);
        $dice = Cell::dice('d4+1');

        self::assertSame([ColumnKind::Text, ColumnKind::Number, ColumnKind::Dice], [$text->kind, $number->kind, $dice->kind]);
        self::assertSame(['Hostile', '-12', '1d4+1'], [$text->display('fr'), $number->display(), $dice->display()]);
        self::assertSame(-12, $number->toInt());
        self::assertSame('1d4+1', $dice->notation());
        self::assertSame('1d4+1', $dice->pool()->canonical());
        self::assertSame(['en' => 'Hostile', 'fr' => 'Hostile'], $text->translations()->texts);
        self::assertSame(Cell::MAX_NUMBER, Cell::number(Cell::MAX_NUMBER)->toInt());
        self::assertSame(-Cell::MAX_NUMBER, Cell::number(-Cell::MAX_NUMBER)->toInt());
    }

    public function testRefusesAnEmptyText(): void
    {
        $this->expectExceptionMessageIsOrContains('A text cell holds no text.');

        Cell::text(new Translations());
    }

    public function testRefusesANumberBeyondItsBound(): void
    {
        $this->expectExceptionMessageIsOrContains('A number cell holds a value from -1000000000 to 1000000000.');

        Cell::number(Cell::MAX_NUMBER + 1);
    }

    public function testRefusesANumberBeyondItsBoundDownwards(): void
    {
        $this->expectException(InvalidTable::class);

        Cell::number(-Cell::MAX_NUMBER - 1);
    }

    public function testRefusesDiceItCannotRead(): void
    {
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains('The dice cell "2d" cannot be read:');

        Cell::dice('2d');
    }

    /**
     * @return iterable<string, array{Cell, callable(Cell): mixed, string}>
     */
    public static function wrongReads(): iterable
    {
        yield 'a number as text' => [Cell::number(1), static fn (Cell $cell): mixed => $cell->translations(), 'The cell holds a number, not a text.'];
        yield 'a text as number' => [Cell::text(Tables::text('A')), static fn (Cell $cell): mixed => $cell->toInt(), 'The cell holds a text, not a number.'];
        yield 'a number as dice' => [Cell::number(1), static fn (Cell $cell): mixed => $cell->notation(), 'The cell holds a number, not dice.'];
        yield 'a text rolled' => [Cell::text(Tables::text('A')), static fn (Cell $cell): mixed => $cell->pool(), 'The cell holds a text, not dice.'];
    }

    /**
     * @param callable(Cell): mixed $read
     */
    #[DataProvider('wrongReads')]
    public function testRefusesToReadACellAsAnotherKind(Cell $cell, callable $read, string $message): void
    {
        $this->expectException(LookupRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        $read($cell);
    }

    public function testARowGivesItsCells(): void
    {
        $row = Row::keyed('plate', ['defence' => Cell::number(5)]);

        self::assertTrue($row->has('defence'));
        self::assertFalse($row->has('cost'));
        self::assertSame('5', $row->display('defence'));
        self::assertSame('', $row->display('cost', 'fr'));
        self::assertSame('Plates', Row::keyed('p', ['name' => Cell::text(Tables::text('Plate', 'Plates'))])->display('name', 'fr'));
        self::assertNull($row->range);
        self::assertNull(Row::ranged(Range::of(3), [])->key);
        $this->expectException(UnknownTable::class);
        $this->expectExceptionMessageIsOrContains('The row "plate" has no cell in the column "cost".');

        $row->cell('cost');
    }

    public function testARowHasAKeyOrARange(): void
    {
        $this->expectExceptionMessageIsOrContains('A row has a key or a range, not both nor neither.');

        new Row('a', Range::of(1), []);
    }

    public function testARowHasAtLeastAKeyOrARange(): void
    {
        $this->expectException(InvalidTable::class);

        new Row(null, null, []);
    }

    public function testARowKeyEndsWithItsLastCharacter(): void
    {
        $this->expectException(InvalidTable::class);

        Row::keyed("plate\n", []);
    }

    public function testARowKeyIsAKey(): void
    {
        $this->expectExceptionMessageIsOrContains('The key of a row "Plate" must be');

        Row::keyed('Plate', []);
    }
}
