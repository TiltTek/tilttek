<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Hex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Hex\Axial;
use Tilttek\Grid\Hex\Cube;
use Tilttek\Grid\Hex\HexGrid;
use Tilttek\Grid\Hex\Orientation;
use Tilttek\Grid\Limits;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;

#[CoversClass(HexGrid::class)]
#[CoversClass(Cube::class)]
final class HexGridTest extends TestCase
{
    public function testPointyCentres(): void
    {
        $grid = new HexGrid(Orientation::Pointy, Point::of(100, 100), Ratio::of(52), Ratio::of(60));

        self::assertSame([100, 100], $grid->centerOf(new Axial(0, 0))->rounded());
        self::assertSame([152, 100], $grid->centerOf(new Axial(1, 0))->rounded());
        self::assertSame([126, 145], $grid->centerOf(new Axial(0, 1))->rounded());
        self::assertSame([74, 55], $grid->centerOf(new Axial(0, -1))->rounded());
    }

    public function testFlatCentres(): void
    {
        $grid = new HexGrid(Orientation::Flat, Point::of(100, 100), Ratio::of(60), Ratio::of(52));

        self::assertSame([145, 126], $grid->centerOf(new Axial(1, 0))->rounded());
        self::assertSame([100, 152], $grid->centerOf(new Axial(0, 1))->rounded());
        self::assertSame([55, 74], $grid->centerOf(new Axial(-1, 0))->rounded());
    }

    /**
     * @return iterable<string, array{Orientation, int, int, string}>
     */
    public static function positions(): iterable
    {
        yield 'pointy, centre' => [Orientation::Pointy, 100, 100, '0,0'];
        yield 'pointy, inside right' => [Orientation::Pointy, 125, 100, '0,0'];
        yield 'pointy, across the right side' => [Orientation::Pointy, 127, 100, '1,0'];
        yield 'pointy, near the top point' => [Orientation::Pointy, 100, 71, '0,0'];
        yield 'pointy, beyond the top point' => [Orientation::Pointy, 101, 69, '1,-1'];
        yield 'pointy, below' => [Orientation::Pointy, 126, 140, '0,1'];
        yield 'flat, centre' => [Orientation::Flat, 100, 100, '0,0'];
        yield 'flat, inside bottom' => [Orientation::Flat, 100, 125, '0,0'];
        yield 'flat, across the bottom side' => [Orientation::Flat, 100, 127, '0,1'];
        yield 'flat, near the left point' => [Orientation::Flat, 71, 100, '0,0'];
        yield 'flat, beyond the left point' => [Orientation::Flat, 69, 101, '-1,1'];
    }

    #[DataProvider('positions')]
    public function testTheHexagonUnderAPosition(Orientation $orientation, int $x, int $y, string $expected): void
    {
        [$width, $height] = Orientation::Pointy === $orientation ? [52, 60] : [60, 52];
        $grid = new HexGrid($orientation, Point::of(100, 100), Ratio::of($width), Ratio::of($height));

        self::assertSame($expected, $grid->cellAt(Point::of($x, $y))->key());
    }

    public function testSnapping(): void
    {
        $grid = new HexGrid(Orientation::Pointy, Point::of(0, 0), Ratio::of(52), Ratio::of(60));

        self::assertTrue($grid->snapToCenter(Point::of(48, 5))->equals(Point::of(52, 0)));
    }

    public function testSizesArePositive(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('A hexagon has a positive width and a positive height.');

        new HexGrid(Orientation::Pointy, Point::of(0, 0), Ratio::of(52), Ratio::of(0));
    }

    public function testWidthIsPositive(): void
    {
        $this->expectException(InvalidGrid::class);

        new HexGrid(Orientation::Pointy, Point::of(0, 0), Ratio::of(0), Ratio::of(60));
    }

    /**
     * @return iterable<string, array{Orientation}>
     */
    public static function orientations(): iterable
    {
        yield 'pointy' => [Orientation::Pointy];
        yield 'flat' => [Orientation::Flat];
    }

    #[DataProvider('orientations')]
    public function testAligningAScannedMap(Orientation $orientation): void
    {
        $truth = new HexGrid($orientation, Point::of(37, 41), Ratio::of(1039, 10), Ratio::of(120));
        $first = new Axial(1, 2);
        $second = new Axial(9, 4);

        $grid = HexGrid::aligned($orientation, $truth->centerOf($first), $first, $truth->centerOf($second), $second);

        self::assertSame($orientation, $grid->orientation);
        self::assertTrue($grid->origin->equals($truth->origin));
        self::assertTrue($grid->width->equals($truth->width));
        self::assertTrue($grid->height->equals($truth->height));
    }

    /**
     * @return iterable<string, array{Orientation, Axial}>
     */
    public static function unhelpfulPairs(): iterable
    {
        yield 'pointy, same row' => [Orientation::Pointy, new Axial(3, 0)];
        yield 'pointy, straight below' => [Orientation::Pointy, new Axial(-1, 2)];
        yield 'flat, same column' => [Orientation::Flat, new Axial(0, 3)];
        yield 'flat, straight across' => [Orientation::Flat, new Axial(2, -1)];
    }

    #[DataProvider('unhelpfulPairs')]
    public function testAligningNeedsTwoUsefulHexagons(Orientation $orientation, Axial $second): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('Two reference hexagons lie in different rows or columns, and not in a straight line across them.');

        HexGrid::aligned($orientation, Point::of(0, 0), new Axial(0, 0), Point::of(300, 300), $second);
    }

    public function testAligningRefusesAFlippedMap(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('The reference hexagons give a size that is not positive: check their coordinates.');

        HexGrid::aligned(Orientation::Pointy, Point::of(0, 0), new Axial(0, 0), Point::of(-100, 100), new Axial(1, 1));
    }

    public function testAligningRefusesAFlippedHeight(): void
    {
        $this->expectException(InvalidGrid::class);

        HexGrid::aligned(Orientation::Pointy, Point::of(0, 0), new Axial(0, 0), Point::of(100, -100), new Axial(1, 1));
    }

    public function testCubeCoordinatesAddUpToZero(): void
    {
        self::assertSame('1,2', new Cube(1, 2, -3)->toAxial()->key());
        self::assertTrue(new Cube(1, 2, -3)->equals(new Cube(1, 2, -3)));
        self::assertFalse(new Cube(1, 2, -3)->equals(new Cube(2, 1, -3)));
        self::assertFalse(new Cube(1, 2, -3)->equals(new Cube(1, 1, -2)));

        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('Cube coordinates add up to zero; 1 + 2 + 3 does not.');

        new Cube(1, 2, 3);
    }

    public function testCubeCoordinatesAreBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Cube(0, Limits::COORDINATE + 1, -Limits::COORDINATE - 1);
    }

    public function testCubeQIsBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Cube(Limits::COORDINATE + 1, 0, -Limits::COORDINATE - 1);
    }

    public function testCubeSIsBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Cube(Limits::COORDINATE, 1, -Limits::COORDINATE - 1);
    }

    public function testNearestHexagon(): void
    {
        // (0.6, 0.6, -1.2): r is fixed last by the axis that moved most.
        self::assertSame('1,0', Cube::nearest(6, 6, -12, 10)->toAxial()->key());
        // (0.4, -0.3, -0.1): the three round to zero.
        self::assertSame('0,0', Cube::nearest(4, -3, -1, 10)->toAxial()->key());
        // (0.2, 0.45, -0.65): r moved most and is fixed by q and s.
        self::assertSame('0,1', Cube::nearest(20, 45, -65, 100)->toAxial()->key());
        // (1.45, -0.6, -0.85): q moved most and is fixed by r and s.
        self::assertSame('2,-1', Cube::nearest(145, -60, -85, 100)->toAxial()->key());
        // (1.4, -0.6, -0.8): q and r moved as much; r is fixed.
        self::assertSame('1,0', Cube::nearest(14, -6, -8, 10)->toAxial()->key());
        // (0.2, -0.6, 0.4): r and s moved as much; s is fixed.
        self::assertSame('0,-1', Cube::nearest(2, -6, 4, 10)->toAxial()->key());
        self::assertSame('1,0', Cube::nearestTo(Ratio::of(3, 4), Ratio::of(-1, 6))->toAxial()->key());
    }
}
