<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\Grid\Hex\Axial;
use Tilttek\Grid\Hex\Cube;
use Tilttek\Grid\Hex\HexGrid;
use Tilttek\Grid\Hex\Offset;
use Tilttek\Grid\Hex\OffsetLayout;
use Tilttek\Grid\Hex\Orientation;
use Tilttek\Grid\Isometric\IsometricGrid;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;
use Tilttek\Grid\Square\Cell;
use Tilttek\Grid\Square\DiagonalRule;
use Tilttek\Grid\Square\Movement;
use Tilttek\Grid\Square\SquareGrid;

/**
 * Properties over random coordinates drawn from fixed seeds: conversions go there and back, the centre of a cell lies
 * in that cell, lines are chains of neighbours as long as the distance, turns keep distances, and areas and rings have
 * the size their radius gives.
 */
#[CoversClass(Axial::class)]
#[CoversClass(Offset::class)]
#[CoversClass(Cube::class)]
#[CoversClass(HexGrid::class)]
#[CoversClass(SquareGrid::class)]
#[CoversClass(IsometricGrid::class)]
#[CoversClass(Movement::class)]
#[CoversClass(Cell::class)]
final class PropertyTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach ([1, 7, 42, 1999, 31337] as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    #[DataProvider('seeds')]
    public function testHexCoordinatesGoThereAndBack(int $seed): void
    {
        $chance = new Randomizer(new Mt19937($seed));
        for ($i = 0; $i < 200; ++$i) {
            $hex = new Axial($chance->getInt(-5000, 5000), $chance->getInt(-5000, 5000));
            self::assertTrue($hex->toCube()->toAxial()->equals($hex));
            foreach (OffsetLayout::cases() as $layout) {
                self::assertTrue($hex->toOffset($layout)->toAxial($layout)->equals($hex));
            }

            self::assertTrue(Axial::fromArray($hex->toArray())->equals($hex));
        }
    }

    #[DataProvider('seeds')]
    public function testTheCentreOfAHexagonLiesInIt(int $seed): void
    {
        $chance = new Randomizer(new Mt19937($seed));
        foreach (Orientation::cases() as $orientation) {
            $grid = new HexGrid($orientation, Point::of($chance->getInt(-500, 500), $chance->getInt(-500, 500)), Ratio::of($chance->getInt(200, 1200), 10), Ratio::of($chance->getInt(200, 1200), 10));
            for ($i = 0; $i < 100; ++$i) {
                $hex = new Axial($chance->getInt(-300, 300), $chance->getInt(-300, 300));
                self::assertTrue($grid->cellAt($grid->centerOf($hex))->equals($hex));
            }
        }
    }

    #[DataProvider('seeds')]
    public function testTheCentreOfASquareLiesInIt(int $seed): void
    {
        $chance = new Randomizer(new Mt19937($seed));
        $square = new SquareGrid(Point::of($chance->getInt(-500, 500), 3), Ratio::of($chance->getInt(200, 1200), 7), Ratio::of($chance->getInt(200, 1200), 9));
        $iso = new IsometricGrid(Point::of($chance->getInt(-500, 500), 3), Ratio::of($chance->getInt(200, 1200), 7), Ratio::of($chance->getInt(200, 1200), 9));
        for ($i = 0; $i < 100; ++$i) {
            $cell = new Cell($chance->getInt(-300, 300), $chance->getInt(-300, 300));
            self::assertTrue($square->cellAt($square->centerOf($cell))->equals($cell));
            self::assertTrue($iso->cellAt($iso->centerOf($cell))->equals($cell));
        }
    }

    #[DataProvider('seeds')]
    public function testHexLinesAreChainsOfNeighbours(int $seed): void
    {
        $chance = new Randomizer(new Mt19937($seed));
        for ($i = 0; $i < 50; ++$i) {
            $from = new Axial($chance->getInt(-50, 50), $chance->getInt(-50, 50));
            $to = new Axial($chance->getInt(-50, 50), $chance->getInt(-50, 50));
            $line = $from->lineTo($to);

            self::assertCount($from->distanceTo($to) + 1, $line);
            self::assertTrue($line[0]->equals($from));
            self::assertTrue($line[\count($line) - 1]->equals($to));
            for ($step = 1, $count = \count($line); $step < $count; ++$step) {
                self::assertSame(1, $line[$step - 1]->distanceTo($line[$step]));
            }
        }
    }

    #[DataProvider('seeds')]
    public function testSquareLinesAreChainsOfNeighbours(int $seed): void
    {
        $chance = new Randomizer(new Mt19937($seed));
        $movement = new Movement();
        for ($i = 0; $i < 50; ++$i) {
            $from = new Cell($chance->getInt(-50, 50), $chance->getInt(-50, 50));
            $to = new Cell($chance->getInt(-50, 50), $chance->getInt(-50, 50));
            $line = $movement->line($from, $to);

            self::assertCount($movement->distance($from, $to) + 1, $line);
            self::assertTrue($line[0]->equals($from));
            self::assertTrue($line[\count($line) - 1]->equals($to));
            for ($step = 1, $count = \count($line); $step < $count; ++$step) {
                self::assertSame(1, $movement->distance($line[$step - 1], $line[$step]));
            }
        }
    }

    #[DataProvider('seeds')]
    public function testTurnsKeepDistances(int $seed): void
    {
        $chance = new Randomizer(new Mt19937($seed));
        $movement = new Movement(DiagonalRule::Euclidean);
        for ($i = 0; $i < 100; ++$i) {
            $pivot = new Axial($chance->getInt(-100, 100), $chance->getInt(-100, 100));
            $hex = new Axial($chance->getInt(-100, 100), $chance->getInt(-100, 100));
            $turns = $chance->getInt(-12, 12);
            self::assertSame($hex->distanceTo($pivot), $hex->rotateAround($pivot, $turns)->distanceTo($pivot));
            self::assertTrue($hex->rotateAround($pivot, $turns)->rotateAround($pivot, -$turns)->equals($hex));

            $center = new Cell($pivot->q, $pivot->r);
            $cell = new Cell($hex->q, $hex->r);
            self::assertSame($movement->distance($center, $cell), $movement->distance($center, $cell->rotateAround($center, $turns)));
            self::assertTrue($cell->rotateAround($center, $turns)->rotateAround($center, -$turns)->equals($cell));
        }
    }

    public function testAreasAndRingsHaveTheirSize(): void
    {
        $center = new Axial(3, -7);
        for ($radius = 0; $radius <= 12; ++$radius) {
            $area = $center->area($radius);
            self::assertCount(3 * $radius * ($radius + 1) + 1, $area);
            self::assertTrue(array_all($area, static fn (Axial $hex): bool => $hex->distanceTo($center) <= $radius));
            $ring = $center->ring($radius);
            self::assertCount(max(1, 6 * $radius), $ring);
            self::assertTrue(array_all($ring, static fn (Axial $hex): bool => $hex->distanceTo($center) === $radius));
        }
    }
}
