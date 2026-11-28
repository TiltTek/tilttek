<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Hex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Hex\Cube;
use Tilttek\Grid\Limits;

#[CoversClass(Cube::class)]
final class CubeTest extends TestCase
{
    public function testItChecksQ(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('q is 1000001');

        new Cube(Limits::COORDINATE + 1, -Limits::COORDINATE, -1);
    }

    public function testItChecksR(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('r is 1000001');

        new Cube(-Limits::COORDINATE, Limits::COORDINATE + 1, -1);
    }

    public function testItChecksS(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('s is 1000001');

        new Cube(-1, -Limits::COORDINATE, Limits::COORDINATE + 1);
    }

    public function testItAddsUpToZero(): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('1 + 1 + 1 does not');

        new Cube(1, 1, 1);
    }

    public function testATieBetweenTwoAxesFixesTheLastOne(): void
    {
        // Q and S moved by the same amount, more than R: the last axis is fixed by the other two.
        $cube = Cube::nearest(-5, -6, 11, 2);

        self::assertSame([-2, -3, 5], [$cube->q, $cube->r, $cube->s]);
    }
}
