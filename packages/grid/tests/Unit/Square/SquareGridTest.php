<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Square;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;
use Tilttek\Grid\Square\Cell;
use Tilttek\Grid\Square\SquareGrid;

#[CoversClass(SquareGrid::class)]
final class SquareGridTest extends TestCase
{
    public function testCellsAndCentres(): void
    {
        $grid = new SquareGrid(Point::of(10, 20), Ratio::of(50), Ratio::of(40));

        self::assertSame('0,0', $grid->cellAt(Point::of(10, 20))->key());
        self::assertSame('0,0', $grid->cellAt(Point::of(59, 59))->key());
        self::assertSame('1,1', $grid->cellAt(Point::of(60, 60))->key());
        self::assertSame('-1,-1', $grid->cellAt(Point::of(9, 19))->key());
        self::assertSame([35, 40], $grid->centerOf(new Cell(0, 0))->rounded());
        self::assertSame([135, 120], $grid->centerOf(new Cell(2, 2))->rounded());
        self::assertTrue($grid->snapToCenter(Point::of(12, 58))->equals(Point::of(35, 40)));
    }

    public function testSnappingToACorner(): void
    {
        $grid = new SquareGrid(Point::of(0, 0), Ratio::of(50), Ratio::of(50));

        self::assertTrue($grid->snapToCorner(Point::of(24, 26))->equals(Point::of(0, 50)));
        self::assertTrue($grid->snapToCorner(Point::of(126, 74))->equals(Point::of(150, 50)));
    }

    public function testPixelsPerCell(): void
    {
        $grid = SquareGrid::ofPixelsPerCell(140);

        self::assertSame('140', (string) $grid->cellWidth);
        self::assertSame('140', (string) $grid->cellHeight);
        self::assertTrue($grid->origin->equals(Point::of(0, 0)));
        self::assertTrue(SquareGrid::ofPixelsPerCell(70, Point::of(5, 6))->origin->equals(Point::of(5, 6)));
    }

    public function testSizesArePositive(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('A square cell has a positive width and a positive height.');

        new SquareGrid(Point::of(0, 0), Ratio::of(50), Ratio::of(0));
    }

    public function testWidthIsPositive(): void
    {
        $this->expectException(InvalidGrid::class);

        new SquareGrid(Point::of(0, 0), Ratio::of(-50), Ratio::of(50));
    }

    public function testAligningAScannedMap(): void
    {
        $truth = new SquareGrid(Point::of(13, 7), Ratio::of(301, 3), Ratio::of(100));
        $first = new Cell(1, 2);
        $second = new Cell(28, 19);

        $grid = SquareGrid::aligned($truth->centerOf($first), $first, $truth->centerOf($second), $second);

        self::assertTrue($grid->origin->equals($truth->origin));
        self::assertTrue($grid->cellWidth->equals($truth->cellWidth));
        self::assertTrue($grid->cellHeight->equals($truth->cellHeight));
    }

    public function testAligningNeedsTwoColumns(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('Two reference cells differ in column and in row.');

        SquareGrid::aligned(Point::of(0, 0), new Cell(1, 1), Point::of(0, 100), new Cell(1, 2));
    }

    public function testAligningNeedsTwoRows(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('Two reference cells differ in column and in row.');

        SquareGrid::aligned(Point::of(0, 0), new Cell(1, 1), Point::of(100, 0), new Cell(2, 1));
    }

    public function testAligningRefusesAFlippedMap(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('The reference cells give a cell size that is not positive: check their coordinates.');

        SquareGrid::aligned(Point::of(0, 0), new Cell(1, 1), Point::of(-100, 100), new Cell(2, 2));
    }

    public function testAligningRefusesAFlippedHeight(): void
    {
        $this->expectException(InvalidGrid::class);

        SquareGrid::aligned(Point::of(0, 0), new Cell(1, 1), Point::of(100, -100), new Cell(2, 2));
    }

    public function testAligningFromCellsAwayFromTheOrigin(): void
    {
        $truth = new SquareGrid(Point::of(30, 20), Ratio::of(50), Ratio::of(40));
        $first = new Cell(3, 4);
        $second = new Cell(9, 6);

        $grid = SquareGrid::aligned($truth->centerOf($first), $first, $truth->centerOf($second), $second);

        self::assertTrue($grid->origin->equals($truth->origin));
        self::assertTrue($grid->centerOf($first)->equals($truth->centerOf($first)));
    }
}
