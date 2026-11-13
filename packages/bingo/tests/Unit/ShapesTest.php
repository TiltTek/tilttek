<?php

declare(strict_types=1);

namespace Tilttek\Bingo\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Bingo\Exception\InvalidBingo;
use Tilttek\Bingo\Grid;
use Tilttek\Bingo\Identifier;
use Tilttek\Bingo\Square;
use Tilttek\Bingo\SquarePool;
use Tilttek\Bingo\Trigger;
use Tilttek\Bingo\WinPattern;
use Tilttek\Bingo\WinPatterns;

#[CoversClass(Grid::class)]
#[CoversClass(Square::class)]
#[CoversClass(SquarePool::class)]
#[CoversClass(Trigger::class)]
#[CoversClass(WinPattern::class)]
#[CoversClass(WinPatterns::class)]
#[CoversClass(Identifier::class)]
#[CoversClass(InvalidBingo::class)]
final class ShapesTest extends TestCase
{
    public function testAGridIsThreeToNineCellsWideWithAnOptionalFreeCentre(): void
    {
        $grid = new Grid();

        self::assertSame(5, $grid->size);
        self::assertTrue($grid->freeCentre);
        self::assertSame(25, $grid->cells());
        self::assertSame(12, $grid->centre());
        self::assertSame(24, $grid->squares());
        self::assertSame(7, $grid->position(1, 2));
        self::assertSame(24, $grid->position(4, 4));
        self::assertSame(0, $grid->position(0, 0));

        $plain = new Grid(4, false);
        self::assertNull($plain->centre());
        self::assertSame(16, $plain->squares());
        self::assertSame(9, new Grid(3)->cells());
        self::assertSame(81, new Grid(9, false)->cells());

        foreach ([[2, false], [10, false], [4, true]] as [$size, $free]) {
            try {
                new Grid($size, $free);
                self::fail('A bad grid was accepted.');
            } catch (InvalidBingo $exception) {
                self::assertSame(4 === $size ? 'A card 4 cells wide has no centre to leave free.' : \sprintf('A card is 3 to 9 cells wide; %d is not allowed.', $size), $exception->getMessage());
            }
        }

        foreach ([[-1, 0], [0, -1], [5, 0], [0, 5]] as [$row, $column]) {
            try {
                $grid->position($row, $column);
                self::fail('A cell outside the grid was accepted.');
            } catch (InvalidBingo $exception) {
                self::assertSame(\sprintf('A card 5 cells wide has no cell at row %d, column %d.', $row, $column), $exception->getMessage());
            }
        }
    }

    public function testASquareHasALabel(): void
    {
        $square = new Square('talk', 'Attend a talk');

        self::assertSame('talk', $square->id);
        self::assertSame('Attend a talk', $square->label);
        self::assertNull($square->trigger);
        self::assertSame(str_repeat('x', 200), new Square('a', str_repeat('x', 200))->label);

        foreach ([' ', str_repeat('x', 201)] as $label) {
            try {
                new Square('a', $label);
                self::fail('A bad label was accepted.');
            } catch (InvalidBingo $exception) {
                self::assertSame('The label of the square a is 1 to 200 bytes of text.', $exception->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('The identifier of a square is 1 to 128 letters, digits, "_", ".", ":", "@" or "-", starting with a letter or a digit.');
        new Square('a talk', 'Talk');
    }

    public function testAPoolHoldsDistinctSquares(): void
    {
        $pool = new SquarePool([new Square('a', 'A'), new Square('b', 'B')]);

        self::assertSame(2, $pool->count());
        self::assertTrue($pool->has('a'));
        self::assertFalse($pool->has('z'));
        self::assertSame('B', $pool->get('b')->label);

        try {
            $pool->get('z');
            self::fail('An unknown square was found.');
        } catch (InvalidBingo $invalidBingo) {
            self::assertSame('The pool has no square z.', $invalidBingo->getMessage());
        }

        self::assertSame(500, new SquarePool(array_map(static fn (int $n): Square => new Square('s'.$n, 'S'), range(1, 500)))->count());

        foreach ([[], array_map(static fn (int $n): Square => new Square('s'.$n, 'S'), range(1, 501))] as $squares) {
            try {
                new SquarePool($squares);
                self::fail('A pool out of bounds was accepted.');
            } catch (InvalidBingo $exception) {
                self::assertSame(\sprintf('A pool holds 1 to 500 squares; this one holds %d.', \count($squares)), $exception->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('Two squares of the pool are a.');
        new SquarePool([new Square('a', 'A'), new Square('a', 'B')]);
    }

    public function testATriggerMatchesAVerbAndMaybeAnObject(): void
    {
        $at = new DateTimeImmutable('2026-11-12 20:00:00 UTC');
        $visit = Activity::of('k1', 'member:1', 'booth.visited', $at, 'booth:7');
        $talk = Activity::of('k2', 'member:1', 'talk.attended', $at);

        self::assertTrue(new Trigger('booth.visited', 'booth:7')->matches($visit));
        self::assertFalse(new Trigger('booth.visited', 'booth:8')->matches($visit));
        self::assertTrue(new Trigger('booth.visited')->matches($visit));
        self::assertTrue(new Trigger('booth.*')->matches($visit));
        self::assertFalse(new Trigger('booth.visited')->matches($talk));
        self::assertFalse(new Trigger('talk.attended', 'room:1')->matches($talk));
        self::assertTrue(new Trigger('*')->matches($talk));
        self::assertSame('booth:7', new Trigger('booth.visited', 'booth:7')->object);
        self::assertSame('booth.*', new Trigger('booth.*')->verb->value);
    }

    public function testTheGivenPatternsCoverRowsColumnsDiagonalsTheFullCardAndCorners(): void
    {
        $grid = new Grid(3, false);

        self::assertSame([[0, 1, 2], [3, 4, 5], [6, 7, 8]], WinPattern::row($grid)->sets);
        self::assertSame('row', WinPattern::row($grid)->name);
        self::assertSame([[0, 3, 6], [1, 4, 7], [2, 5, 8]], WinPattern::column($grid)->sets);
        self::assertSame('column', WinPattern::column($grid)->name);
        self::assertSame([[0, 4, 8], [2, 4, 6]], WinPattern::diagonal($grid)->sets);
        self::assertSame('diagonal', WinPattern::diagonal($grid)->name);
        self::assertSame([range(0, 8)], WinPattern::fullCard($grid)->sets);
        self::assertSame('full-card', WinPattern::fullCard($grid)->name);
        self::assertSame([[0, 2, 6, 8]], WinPattern::fourCorners($grid)->sets);
        self::assertSame('four-corners', WinPattern::fourCorners($grid)->name);
    }

    public function testAPatternIsMatchedWhenOneOfItsSetsIsAllTicked(): void
    {
        $row = WinPattern::row(new Grid(3, false));

        self::assertTrue($row->matchedBy([3 => true, 4 => true, 5 => true]));
        self::assertTrue($row->matchedBy([0 => true, 3 => true, 4 => true, 5 => true]));
        self::assertFalse($row->matchedBy([0 => true, 1 => true, 3 => true]));
        self::assertFalse($row->matchedBy([]));
    }

    public function testACustomPatternIsChecked(): void
    {
        $grid = new Grid(3, false);
        $cross = WinPattern::custom('cross', $grid, [[1, 3, 4, 5, 7, 7]]);

        self::assertSame('cross', $cross->name);
        self::assertSame([[1, 3, 4, 5, 7]], $cross->sets);
        self::assertCount(64, WinPattern::custom('many', $grid, array_fill(0, 64, [0]))->sets);
        self::assertSame([[0], [8]], WinPattern::custom('edges', $grid, [[0], [8]])->sets);

        foreach ([
            'The win pattern none holds 1 to 64 sets of cells; it holds 0.' => [],
            'The win pattern none holds 1 to 64 sets of cells; it holds 65.' => array_fill(0, 65, [0]),
            'A set of cells of the win pattern none is empty.' => [[]],
            'The win pattern none names the cell 9, outside a card of 9 cells.' => [[9]],
            'The win pattern none names the cell -1, outside a card of 9 cells.' => [[-1]],
        ] as $message => $sets) {
            try {
                WinPattern::custom('none', $grid, $sets);
                self::fail('A bad custom pattern was accepted.');
            } catch (InvalidBingo $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        $this->expectException(InvalidBingo::class);
        WinPattern::custom('a cross', $grid, [[0]]);
    }

    public function testPatternsAreCheckedInOrder(): void
    {
        $grid = new Grid(3, false);
        $line = WinPatterns::line($grid);

        self::assertSame(['row', 'column', 'diagonal'], array_map(static fn (WinPattern $pattern): string => $pattern->name, $line->patterns));
        self::assertSame('column', $line->matched([1 => true, 4 => true, 7 => true])?->name);
        self::assertSame('row', $line->matched(array_fill_keys(range(0, 8), true))?->name);
        self::assertNull($line->matched([0 => true]));

        foreach ([
            'A game is won in 1 to 16 ways; this one is won in 0.' => [],
            'A game is won in 1 to 16 ways; this one is won in 17.' => array_map(static fn (int $n): WinPattern => WinPattern::custom('p'.$n, $grid, [[0]]), range(1, 17)),
            'Two win patterns are named row.' => [WinPattern::row($grid), WinPattern::row($grid)],
        ] as $message => $patterns) {
            try {
                new WinPatterns(...$patterns);
                self::fail('Bad patterns were accepted.');
            } catch (InvalidBingo $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }

        self::assertCount(16, new WinPatterns(...array_map(static fn (int $n): WinPattern => WinPattern::custom('p'.$n, $grid, [[0]]), range(1, 16)))->patterns);
    }

    public function testAPatternKeepsEachCellOnceAndPatternsTheirOrder(): void
    {
        $grid = new Grid(3);
        $cross = WinPattern::custom('cross', $grid, [[4, 4, 1]]);

        self::assertSame([[4, 1]], $cross->sets);
        self::assertSame([$cross], new WinPatterns(...['only' => $cross])->patterns);
        self::assertCount(1, new WinPatterns($cross)->patterns);
    }
}
