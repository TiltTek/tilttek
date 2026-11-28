<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;

#[CoversClass(Limits::class)]
final class LimitsTest extends TestCase
{
    public function testCoordinatesAreBounded(): void
    {
        self::assertSame(Limits::COORDINATE, Limits::coordinate(Limits::COORDINATE, 'x'));
        self::assertSame(-Limits::COORDINATE, Limits::coordinate(-Limits::COORDINATE, 'x'));

        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('A coordinate lies from -1000000 to 1000000; q is 1000001.');

        Limits::coordinate(Limits::COORDINATE + 1, 'q');
    }

    public function testNegativeCoordinatesAreBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        Limits::coordinate(-Limits::COORDINATE - 1, 'x');
    }

    public function testRadiiAreBounded(): void
    {
        self::assertSame(0, Limits::radius(0, 5, 'an area'));
        self::assertSame(5, Limits::radius(5, 5, 'an area'));

        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('The radius of an area lies from 0 to 5; it is 6.');

        Limits::radius(6, 5, 'an area');
    }

    public function testNegativeRadiiAreRefused(): void
    {
        $this->expectException(OutOfBounds::class);

        Limits::radius(-1, 5, 'a ring');
    }

    public function testLinesAreBounded(): void
    {
        self::assertSame(Limits::LINE, Limits::line(Limits::LINE));

        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('A line has at most 10000 steps; this one has 10001.');

        Limits::line(Limits::LINE + 1);
    }

    public function testFloorDivisionRoundsDown(): void
    {
        self::assertSame(2, Limits::floorDiv(7, 3));
        self::assertSame(-3, Limits::floorDiv(-7, 3));
        self::assertSame(-2, Limits::floorDiv(-6, 3));
        self::assertSame(0, Limits::floorDiv(0, 3));
    }

    public function testRoundingHalfUp(): void
    {
        self::assertSame(3, Limits::roundHalfUp(5, 2));
        self::assertSame(-2, Limits::roundHalfUp(-5, 2));
        self::assertSame(2, Limits::roundHalfUp(7, 3));
        self::assertSame(-2, Limits::roundHalfUp(-7, 3));
    }
}
