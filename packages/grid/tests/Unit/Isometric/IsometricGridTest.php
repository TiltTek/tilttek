<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Isometric;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Isometric\IsometricGrid;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;
use Tilttek\Grid\Square\Cell;

#[CoversClass(IsometricGrid::class)]
final class IsometricGridTest extends TestCase
{
    public function testDiamonds(): void
    {
        $grid = new IsometricGrid(Point::of(500, 100), Ratio::of(64), Ratio::of(32));

        self::assertSame([500, 116], $grid->centerOf(new Cell(0, 0))->rounded());
        self::assertSame([532, 132], $grid->centerOf(new Cell(1, 0))->rounded());
        self::assertSame([468, 132], $grid->centerOf(new Cell(0, 1))->rounded());
        self::assertSame([500, 148], $grid->centerOf(new Cell(1, 1))->rounded());
        self::assertSame('0,0', $grid->cellAt(Point::of(500, 116))->key());
        self::assertSame('0,0', $grid->cellAt(Point::of(470, 116))->key());
        self::assertSame('1,0', $grid->cellAt(Point::of(532, 132))->key());
        self::assertSame('0,1', $grid->cellAt(Point::of(468, 132))->key());
        self::assertSame('-1,-1', $grid->cellAt(Point::of(500, 90))->key());
        self::assertSame('3,-2', $grid->cellAt($grid->centerOf(new Cell(3, -2)))->key());
        self::assertTrue($grid->snapToCenter(Point::of(530, 135))->equals(Point::of(532, 132)));
    }

    public function testSizesArePositive(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('An isometric cell has a positive width and a positive height.');

        new IsometricGrid(Point::of(0, 0), Ratio::of(64), Ratio::of(0));
    }

    public function testWidthIsPositive(): void
    {
        $this->expectException(InvalidGrid::class);

        new IsometricGrid(Point::of(0, 0), Ratio::of(0), Ratio::of(32));
    }
}
