<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Benchmark;

use Tilttek\Grid\Hex\Axial;
use Tilttek\Grid\Hex\HexGrid;
use Tilttek\Grid\Hex\Orientation;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;

/**
 * What a map request does most: find the hexagon under a pointer on a scanned map, draw a line, and compute the field
 * of view of a token at the largest radius, with a wall every seventh hexagon.
 */
final readonly class GridBench
{
    private HexGrid $grid;

    public function __construct()
    {
        $this->grid = new HexGrid(Orientation::Pointy, Point::of(37, 41), Ratio::of(1039, 10), Ratio::of(120));
    }

    public function benchHexagonUnderAPointer(): void
    {
        for ($i = 0; $i < 1_000; ++$i) {
            $this->grid->cellAt(Point::of($i * 7, $i * 3));
        }
    }

    public function benchLineOfOneHundredSteps(): void
    {
        new Axial(0, 0)->lineTo(new Axial(70, -30));
    }

    public function benchFieldOfViewAtTheLargestRadius(): void
    {
        new Axial(0, 0)->fieldOfView(30, static fn (Axial $hex): bool => 0 === ($hex->q * 3 + $hex->r) % 7 && (0 !== $hex->q || 0 !== $hex->r));
    }
}
