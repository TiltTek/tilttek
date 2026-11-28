<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit\Rolling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\ReferenceTables\Cell;
use Tilttek\ReferenceTables\Column;
use Tilttek\ReferenceTables\ColumnKind;
use Tilttek\ReferenceTables\Event\TableRolled;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Exception\LookupRefused;
use Tilttek\ReferenceTables\Exception\UnknownRow;
use Tilttek\ReferenceTables\Lookup;
use Tilttek\ReferenceTables\Range;
use Tilttek\ReferenceTables\ReferenceTable;
use Tilttek\ReferenceTables\Rolling\TableRoll;
use Tilttek\ReferenceTables\Rolling\TableRoller;
use Tilttek\ReferenceTables\Row;
use Tilttek\ReferenceTables\Tests\Unit\Fixture\Observed;
use Tilttek\ReferenceTables\Tests\Unit\Tables;

#[CoversClass(TableRoller::class)]
#[CoversClass(TableRoll::class)]
#[CoversClass(TableRolled::class)]
final class TableRollerTest extends TestCase
{
    private Observed $observed;

    private TableRoller $roller;

    protected function setUp(): void
    {
        $this->observed = new Observed();
        $this->roller = new TableRoller(new DiceRoller(), $this->observed);
    }

    public function testRollsTheDieAndFindsItsRow(): void
    {
        $table = Tables::ranged(['1' => 'One', '2' => 'Two', '3' => 'Three', '4' => 'Four', '5' => 'Five', '6' => 'Six'], '1d6');

        $roll = $this->roller->roll($table, $this->seeded());
        $again = new TableRoller()->roll($table, $this->seeded());

        self::assertSame($roll->natural, $again->natural);
        self::assertSame($roll->natural, $roll->total);
        self::assertSame(0, $roll->modifier);
        self::assertSame((string) $roll->natural, $roll->row->label());
        self::assertSame($roll->natural - 1, $roll->position);
        self::assertSame('reaction', $roll->table);
        self::assertFalse($roll->clamped);
        self::assertFalse($roll->isPhysical());
        self::assertSame([$roll->natural], $roll->dice?->totals());
        self::assertEquals([new TableRolled('reaction', $roll->position, $roll->total, false)], $this->observed->events);
    }

    public function testCoversEveryFaceOfItsDie(): void
    {
        $table = Tables::ranged(['1-2' => 'Low', '3-4' => 'Middle', '5-6' => 'High'], '1d6');
        $randomizer = $this->seeded();
        $seen = [];

        for ($roll = 0; $roll < 60; ++$roll) {
            $seen[$this->roller->roll($table, $randomizer)->row->display('result')] = true;
        }

        self::assertEqualsCanonicalizing(['Low', 'Middle', 'High'], array_keys($seen));
    }

    public function testAddsTheModifierBeforeTheLookup(): void
    {
        $table = Tables::ranged(['≤10' => 'Low', '11+' => 'High'], '1d6');

        $roll = $this->roller->roll($table, $this->seeded(), 10);

        self::assertSame($roll->natural + 10, $roll->total);
        self::assertSame(10, $roll->modifier);
        self::assertSame('High', $roll->row->display('result'));
        self::assertSame($roll->total, $this->observed->events[0]->total);
    }

    public function testEntersADieRolledAtTheTable(): void
    {
        $table = Tables::ranged(['2' => 'Hostile', '3-5' => 'Wary', '6-8' => 'Uncertain'], '2d6');

        $roll = $this->roller->enter($table, 4, 2);

        self::assertSame([4, 2, 6], [$roll->natural, $roll->modifier, $roll->total]);
        self::assertSame('Uncertain', $roll->row->display('result'));
        self::assertSame(2, $roll->position);
        self::assertTrue($roll->isPhysical());
        self::assertNull($roll->dice);
        self::assertEquals([new TableRolled('reaction', 2, 6, true)], $this->observed->events);
    }

    public function testEntersAValueOnATableWithoutADie(): void
    {
        $table = Tables::ranged(['1-4' => 'A', '5-8' => 'B'], null);

        $roll = $this->roller->enter($table, 7);

        self::assertSame('B', $roll->row->display('result'));
        self::assertSame([7, 0, 7, 1], [$roll->natural, $roll->modifier, $roll->total, $roll->position]);
    }

    public function testClampsATotalBeyondTheTableWhenAsked(): void
    {
        $table = Tables::ranged(['2-4' => 'Low', '5-9' => 'Middle', '10-12' => 'High'], '2d6');

        $above = $this->roller->enter($table, 12, 5, clamp: true);
        $below = $this->roller->enter($table, 2, -3, clamp: true);
        $inside = $this->roller->enter($table, 5, clamp: true);

        self::assertSame(['High', 17, true, 2], [$above->row->display('result'), $above->total, $above->clamped, $above->position]);
        self::assertSame(['Low', -1, true, 0], [$below->row->display('result'), $below->total, $below->clamped, $below->position]);
        self::assertSame(['Middle', false], [$inside->row->display('result'), $inside->clamped]);
        self::assertSame([2, 0, 1], array_map(static fn (TableRolled $event): int => $event->position, $this->observed->events));
    }

    public function testClampsAtTheEdgesOnly(): void
    {
        $table = Tables::ranged(['2-4' => 'Low', '10-12' => 'High'], '2d6');

        self::assertSame('Low', $this->roller->enter($table, 1, clamp: true)->row->display('result'));
        self::assertSame('High', $this->roller->enter($table, 13, clamp: true)->row->display('result'));
        $this->expectException(UnknownRow::class);
        $this->expectExceptionMessageIsOrContains('No row of the table "reaction" covers 7.');

        $this->roller->enter($table, 7, clamp: true);
    }

    public function testRefusesATotalBeyondTheTableWithoutClamping(): void
    {
        $table = Tables::ranged(['2-4' => 'Low', '5-12' => 'High'], '2d6');

        try {
            $this->roller->enter($table, 12, 1);
            self::fail('A total beyond the table is refused.');
        } catch (UnknownRow $unknownRow) {
            self::assertSame('No row of the table "reaction" covers 13.', $unknownRow->getMessage());
        }

        self::assertSame([], $this->observed->events);
    }

    public function testRefusesARollBeyondTheTableWithoutClamping(): void
    {
        $this->expectException(UnknownRow::class);

        $this->roller->roll(Tables::ranged(['1-6' => 'A'], '1d6'), $this->seeded(), 10);
    }

    public function testRefusesATableWithoutADie(): void
    {
        $this->expectException(LookupRefused::class);
        $this->expectExceptionMessageIsOrContains('The table "reaction" has no die to roll.');

        $this->roller->roll(Tables::ranged(['1' => 'A'], null), $this->seeded());
    }

    public function testRefusesADieThatGivesNoNumber(): void
    {
        $table = Tables::ranged(['1' => 'A'], null);

        $this->expectException(LookupRefused::class);
        $this->expectExceptionMessageIsOrContains('The die of the table "weather" gives no number.');

        $this->roller->roll(new ReferenceTable('weather', $table->names, $table->provenance, $table->columns, Lookup::Range, $table->rows, '"sun"'), $this->seeded());
    }

    public function testRefusesAModifierBeyondTheLimit(): void
    {
        $table = Tables::ranged(['1' => 'A'], null);

        self::assertSame(Range::LIMIT + 1, $this->roller->enter($table, 1, Range::LIMIT, clamp: true)->total);
        self::assertSame(-Range::LIMIT + 1, $this->roller->enter($table, 1, -Range::LIMIT, clamp: true)->total);
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains('A modifier lies from -1000000 to 1000000.');

        $this->roller->enter($table, 1, Range::LIMIT + 1);
    }

    public function testRefusesAValueBeyondTheLimit(): void
    {
        $table = Tables::ranged(['1' => 'A'], null);

        self::assertSame(Range::LIMIT, $this->roller->enter($table, Range::LIMIT, clamp: true)->natural);
        self::assertSame(-Range::LIMIT, $this->roller->enter($table, -Range::LIMIT, clamp: true)->natural);
        $this->expectException(InvalidTable::class);
        $this->expectExceptionMessageIsOrContains('A value entered lies from -1000000 to 1000000.');

        $this->roller->enter($table, -Range::LIMIT - 1);
    }

    public function testRefusesAModifierBeyondTheLimitDownwards(): void
    {
        $this->expectException(InvalidTable::class);

        $this->roller->enter(Tables::ranged(['1' => 'A'], null), 1, -Range::LIMIT - 1);
    }

    public function testRollsTheDiceOfACell(): void
    {
        $table = new ReferenceTable('wandering', Tables::text('Wandering'), Tables::source(), [new Column('number', Tables::text('Number'), ColumnKind::Dice)], Lookup::Range, [Row::ranged(Range::of(1), ['number' => Cell::dice('3d6')])]);

        $result = $this->roller->rollCell($table->rows[0], 'number', $this->seeded());
        $total = $result->totals()[0];

        self::assertSame($result->totals(), new DiceRoller()->roll($table->rows[0]->cell('number')->pool(), $this->seeded())->totals());
        self::assertGreaterThanOrEqual(3, $total);
        self::assertLessThanOrEqual(18, $total);
        self::assertSame([], $this->observed->events);
    }

    private function seeded(): Randomizer
    {
        return new Randomizer(new Mt19937(20261125));
    }
}
