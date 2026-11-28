<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Square;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;
use Tilttek\Grid\Square\Cell;

#[CoversClass(Cell::class)]
final class CellTest extends TestCase
{
    public function testItIsWrittenAndReadBack(): void
    {
        $cell = new Cell(3, -4);

        self::assertSame(['x' => 3, 'y' => -4], $cell->toArray());
        self::assertTrue(Cell::fromArray(['x' => 3, 'y' => -4])->equals($cell));
        self::assertSame('3,-4', $cell->key());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function badArrays(): iterable
    {
        yield 'no y' => [['x' => 1]];
        yield 'no x' => [['y' => 1]];
        yield 'text' => [['x' => '1', 'y' => 1]];
        yield 'text y' => [['x' => 1, 'y' => '1']];
        yield 'extra key' => [['x' => 1, 'y' => 1, 'z' => 1]];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('badArrays')]
    public function testABadArrayIsRefused(array $data): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('A square cell is written as {"x": int, "y": int}.');

        Cell::fromArray($data);
    }

    public function testCoordinatesAreBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Cell(0, Limits::COORDINATE + 1);
    }

    public function testXIsBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Cell(Limits::COORDINATE + 1, 0);
    }

    public function testEquality(): void
    {
        self::assertTrue(new Cell(1, 2)->equals(new Cell(1, 2)));
        self::assertFalse(new Cell(1, 2)->equals(new Cell(2, 2)));
        self::assertFalse(new Cell(1, 2)->equals(new Cell(1, 3)));
    }

    public function testNeighbors(): void
    {
        $keys = static fn (array $cells): array => array_map(static fn (Cell $cell): string => $cell->key(), $cells);

        self::assertSame(['1,0', '0,1', '-1,0', '0,-1'], $keys(new Cell(0, 0)->neighbors(false)));
        self::assertSame(['1,0', '0,1', '-1,0', '0,-1', '1,1', '-1,1', '-1,-1', '1,-1'], $keys(new Cell(0, 0)->neighbors()));
        self::assertSame('6,7', new Cell(5, 5)->plus(1, 2)->key());
    }

    public function testRotationTurnsClockwiseAsTheMapIsSeen(): void
    {
        $pivot = new Cell(2, 2);
        $east = new Cell(4, 3);

        self::assertSame('1,4', $east->rotateAround($pivot, 1)->key());
        self::assertSame('0,1', $east->rotateAround($pivot, 2)->key());
        self::assertSame('3,0', $east->rotateAround($pivot, 3)->key());
        self::assertSame('4,3', $east->rotateAround($pivot, 4)->key());
        self::assertSame('3,0', $east->rotateAround($pivot, -1)->key());
        self::assertSame('1,4', $east->rotateAround($pivot, 5)->key());
        self::assertSame('2,2', $pivot->rotateAround($pivot, 1)->key());
    }
}
