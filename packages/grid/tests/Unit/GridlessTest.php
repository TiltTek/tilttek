<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\GridKind;
use Tilttek\Grid\Gridless;
use Tilttek\Grid\Point;
use Tilttek\Grid\Ratio;

#[CoversClass(Gridless::class)]
#[CoversClass(Point::class)]
final class GridlessTest extends TestCase
{
    public function testDistancesInUnits(): void
    {
        // 14 pixels to a foot.
        $map = new Gridless(Ratio::of(14));

        self::assertSame(5, $map->distance(Point::of(0, 0), Point::of(42, 56)));
        self::assertSame(1, $map->distance(Point::of(0, 0), Point::of(14, 14)));
        self::assertSame(2, new Gridless(Ratio::of(14), RoundingMode::PositiveInfinity)->distance(Point::of(0, 0), Point::of(14, 14)));
        self::assertSame(0, $map->distance(Point::of(7, 7), Point::of(7, 7)));
        self::assertSame(RoundingMode::HalfAwayFromZero, $map->rounding);
    }

    public function testAUnitSpansPixels(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('A unit spans a positive number of pixels.');

        new Gridless(Ratio::of(0));
    }

    public function testPoints(): void
    {
        $point = new Point(Ratio::of(5, 2), Ratio::of(-7, 2));

        self::assertSame([3, -4], $point->rounded());
        self::assertSame([2, -3], $point->rounded(RoundingMode::HalfTowardsZero));
        self::assertTrue($point->plus(Ratio::of(1, 2), Ratio::of(1, 2))->equals(Point::of(3, -3)));
        self::assertFalse(Point::of(1, 2)->equals(Point::of(1, 3)));
        self::assertFalse(Point::of(1, 2)->equals(Point::of(2, 2)));
        self::assertSame('25', (string) Point::of(1, 1)->squaredDistanceTo(Point::of(4, 5)));
    }

    public function testGridKinds(): void
    {
        self::assertSame(GridKind::HexFlat, GridKind::from('hex-flat'));
        self::assertSame(['square', 'hex-pointy', 'hex-flat', 'isometric', 'none'], array_map(static fn (GridKind $kind): string => $kind->value, GridKind::cases()));
    }
}
