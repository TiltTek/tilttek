<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\Cell;
use Tilttek\MiniGames\CellSymbols;
use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\ShareGrid;

#[CoversClass(ShareGrid::class)]
#[CoversClass(CellSymbols::class)]
#[CoversClass(InvalidMiniGame::class)]
final class ShareGridTest extends TestCase
{
    public function testTheResultAsTextInSquares(): void
    {
        $grid = new ShareGrid([[Cell::Hit, Cell::Near, Cell::Miss], [Cell::Hit, Cell::Hit, Cell::Empty]], '2/10');

        self::assertSame("Bulls and Cows 12 2/10\n\n\u{1F7E9}\u{1F7E8}\u{2B1B}\n\u{1F7E9}\u{1F7E9}\u{2B1C}", $grid->toText('Bulls and Cows 12'));
        self::assertSame($grid->toText('Bulls and Cows 12', CellSymbols::squares()), $grid->toText('Bulls and Cows 12'));
    }

    public function testTheResultInPlainCharacters(): void
    {
        $grid = new ShareGrid([[Cell::Hit, Cell::Near, Cell::Miss, Cell::Empty]], 'X/6');

        self::assertSame("Game X/6\n\n#+.-", $grid->toText('Game', CellSymbols::plain()));
    }

    public function testTheTitleIsWrittenOnOneLine(): void
    {
        $grid = new ShareGrid([], '3/10');

        self::assertSame('Bulls and Cows 3 3/10', $grid->toText("  Bulls  and\nCows\t3 "));
        self::assertSame('3/10', $grid->toText(''));
        self::assertSame('3/10', $grid->toText("\xFF"));
        self::assertSame('Game', new ShareGrid([], '')->toText('Game'));
    }

    public function testTheBoundsAreAccepted(): void
    {
        $grid = new ShareGrid(array_fill(0, 32, array_fill(0, 16, Cell::Hit)), str_repeat('9', 32));

        self::assertCount(32, $grid->rows);
        self::assertSame(str_repeat('9', 32), $grid->summary);
    }

    public function testTooManyRows(): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('A shared result has at most 32 rows.');

        new ShareGrid(array_fill(0, 33, [Cell::Hit]), '');
    }

    public function testTooManyCells(): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('A row of a shared result has at most 16 cells.');

        new ShareGrid([[Cell::Hit], array_fill(0, 17, Cell::Hit)], '');
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badSummaries(): iterable
    {
        yield 'too long' => [str_repeat('9', 33)];
        yield 'outside ASCII' => ['é'];
        yield 'on two lines' => ["1\n2"];
        yield 'a trailing line break' => ["12\n"];
    }

    #[DataProvider('badSummaries')]
    public function testARefusedSummary(string $summary): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('The summary of a shared result is up to 32 printable ASCII characters.');

        new ShareGrid([], $summary);
    }

    public function testTheSymbolsOfEachCell(): void
    {
        $symbols = new CellSymbols('a', 'b', 'c', str_repeat('d', 16));

        self::assertSame('a', $symbols->of(Cell::Hit));
        self::assertSame('b', $symbols->of(Cell::Near));
        self::assertSame('c', $symbols->of(Cell::Miss));
        self::assertSame(str_repeat('d', 16), $symbols->of(Cell::Empty));
        self::assertSame("\u{1F7E9}", CellSymbols::squares()->hit);
        self::assertSame("\u{1F7E8}", CellSymbols::squares()->near);
        self::assertSame("\u{2B1B}", CellSymbols::squares()->miss);
        self::assertSame("\u{2B1C}", CellSymbols::squares()->empty);
        self::assertSame(['#', '+', '.', '-'], [CellSymbols::plain()->hit, CellSymbols::plain()->near, CellSymbols::plain()->miss, CellSymbols::plain()->empty]);
    }

    /**
     * @return iterable<string, array{string, string, string, string}>
     */
    public static function badSymbols(): iterable
    {
        yield 'an empty hit' => ['', 'b', 'c', 'd'];
        yield 'an empty near' => ['a', '', 'c', 'd'];
        yield 'an empty miss' => ['a', 'b', '', 'd'];
        yield 'an empty cell' => ['a', 'b', 'c', ''];
        yield 'too long' => ['a', 'b', 'c', str_repeat('d', 17)];
        yield 'not UTF-8' => ["\xFF", 'b', 'c', 'd'];
        yield 'a line feed' => ['a', "b\n", 'c', 'd'];
        yield 'a carriage return' => ['a', 'b', "\rc", 'd'];
    }

    #[DataProvider('badSymbols')]
    public function testARefusedSymbol(string $hit, string $near, string $miss, string $empty): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('A symbol of a shared result is 1 to 16 bytes of UTF-8 on one line.');

        new CellSymbols($hit, $near, $miss, $empty);
    }
}
