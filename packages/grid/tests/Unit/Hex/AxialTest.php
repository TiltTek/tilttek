<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Hex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Hex\Axial;
use Tilttek\Grid\Hex\OffsetLayout;
use Tilttek\Grid\Limits;

#[CoversClass(Axial::class)]
final class AxialTest extends TestCase
{
    /**
     * @param list<Axial> $hexes
     *
     * @return list<string>
     */
    private function keys(array $hexes): array
    {
        return array_map(static fn (Axial $hex): string => $hex->key(), $hexes);
    }

    public function testItIsWrittenAndReadBack(): void
    {
        $hex = new Axial(2, -5);

        self::assertSame(['q' => 2, 'r' => -5], $hex->toArray());
        self::assertTrue(Axial::fromArray(['q' => 2, 'r' => -5])->equals($hex));
        self::assertSame('2,-5', $hex->key());
        self::assertSame(3, $hex->s());
        self::assertSame(3, $hex->toCube()->s);
        self::assertSame(2, $hex->toCube()->q);
        self::assertSame(-5, $hex->toCube()->r);
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function badArrays(): iterable
    {
        yield 'no r' => [['q' => 1]];
        yield 'no q' => [['r' => 1]];
        yield 'text q' => [['q' => '1', 'r' => 1]];
        yield 'text r' => [['q' => 1, 'r' => '1']];
        yield 'extra key' => [['q' => 1, 'r' => 1, 's' => -2]];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('badArrays')]
    public function testABadArrayIsRefused(array $data): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('Axial coordinates are written as {"q": int, "r": int}.');

        Axial::fromArray($data);
    }

    public function testSIsBounded(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('s is -1000001');

        new Axial(Limits::COORDINATE, 1);
    }

    public function testQIsBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Axial(Limits::COORDINATE + 1, -Limits::COORDINATE);
    }

    public function testRIsBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Axial(-Limits::COORDINATE, Limits::COORDINATE + 1);
    }

    public function testEquality(): void
    {
        self::assertTrue(new Axial(1, 2)->equals(new Axial(1, 2)));
        self::assertFalse(new Axial(1, 2)->equals(new Axial(2, 2)));
        self::assertFalse(new Axial(1, 2)->equals(new Axial(1, 3)));
    }

    public function testVectorArithmetic(): void
    {
        self::assertSame('4,-1', new Axial(1, 2)->plus(new Axial(3, -3))->key());
        self::assertSame('-2,5', new Axial(1, 2)->minus(new Axial(3, -3))->key());
        self::assertSame('3,-6', new Axial(1, -2)->scale(3)->key());
    }

    public function testDistance(): void
    {
        self::assertSame(0, new Axial(4, 4)->distanceTo(new Axial(4, 4)));
        self::assertSame(3, new Axial(0, 0)->distanceTo(new Axial(3, -1)));
        self::assertSame(3, new Axial(0, 0)->distanceTo(new Axial(-3, 0)));
        self::assertSame(5, new Axial(-2, 1)->distanceTo(new Axial(1, 3)));
        self::assertSame(4, new Axial(0, 0)->distanceTo(new Axial(2, -4)));
    }

    public function testNeighbors(): void
    {
        $origin = new Axial(0, 0);

        self::assertSame(['1,0', '1,-1', '0,-1', '-1,0', '-1,1', '0,1'], $this->keys($origin->neighbors()));
        self::assertSame(['2,-1', '1,-2', '-1,-1', '-2,1', '-1,2', '1,1'], $this->keys($origin->diagonals()));
        self::assertSame('1,0', $origin->neighbor(0)->key());
        self::assertSame('0,1', $origin->neighbor(5)->key());
        self::assertSame('1,0', $origin->neighbor(6)->key());
        self::assertSame('0,1', $origin->neighbor(-1)->key());
        self::assertSame('-1,1', $origin->neighbor(-2)->key());
        self::assertSame('6,4', new Axial(5, 4)->neighbor(0)->key());
    }

    public function testRotationTurnsClockwise(): void
    {
        $pivot = new Axial(1, 1);
        $east = new Axial(3, 1);

        self::assertSame('1,3', $east->rotateAround($pivot, 1)->key());
        self::assertSame('-1,3', $east->rotateAround($pivot, 2)->key());
        self::assertSame('-1,1', $east->rotateAround($pivot, 3)->key());
        self::assertSame('3,-1', $east->rotateAround($pivot, -1)->key());
        self::assertSame('3,1', $east->rotateAround($pivot, 6)->key());
        self::assertSame('1,3', $east->rotateAround($pivot, 7)->key());
    }

    public function testLines(): void
    {
        $origin = new Axial(0, 0);

        self::assertSame(['0,0'], $this->keys($origin->lineTo($origin)));
        self::assertSame(['0,0', '1,0', '2,-1', '3,-1'], $this->keys($origin->lineTo(new Axial(3, -1))));
        self::assertSame(['0,0', '1,0', '2,0'], $this->keys($origin->lineTo(new Axial(2, 0))));
        self::assertSame(['0,0', '0,1', '1,1'], $this->keys($origin->lineTo(new Axial(1, 1))));
        self::assertSame(['3,-1', '2,-1', '1,0', '0,0'], $this->keys(new Axial(3, -1)->lineTo($origin)));
        self::assertSame(['-1,0', '-1,1', '-2,2'], $this->keys(new Axial(-1, 0)->lineTo(new Axial(-2, 2))));
    }

    public function testATooLongLineIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);

        new Axial(0, 0)->lineTo(new Axial(Limits::LINE + 1, 0));
    }

    public function testAreasAndRings(): void
    {
        $center = new Axial(2, -1);

        self::assertSame(['2,-1'], $this->keys($center->area(0)));
        self::assertSame(['1,-1', '1,0', '2,-2', '2,-1', '2,0', '3,-2', '3,-1'], $this->keys($center->area(1)));
        self::assertCount(19, $center->area(2));
        self::assertSame(['2,-1'], $this->keys($center->ring(0)));
        self::assertSame(['1,0', '2,0', '3,-1', '3,-2', '2,-2', '1,-1'], $this->keys($center->ring(1)));
        self::assertCount(12, $center->ring(2));
        self::assertCount(6 * Limits::RADIUS, $center->ring(Limits::RADIUS));
        self::assertCount(3 * Limits::RADIUS * (Limits::RADIUS + 1) + 1, $center->area(Limits::RADIUS));
    }

    public function testAnAreaTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('The radius of an area');

        new Axial(0, 0)->area(Limits::RADIUS + 1);
    }

    public function testARingTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('The radius of a ring');

        new Axial(0, 0)->ring(Limits::RADIUS + 1);
    }

    public function testAFieldOfViewStopsAtWalls(): void
    {
        $wall = new Axial(1, 0);
        $seen = $this->keys(new Axial(0, 0)->fieldOfView(2, static fn (Axial $hex): bool => $hex->equals($wall)));

        self::assertSame('0,0', $seen[0]);
        self::assertContains('1,0', $seen);
        self::assertNotContains('2,0', $seen);
        self::assertContains('-2,0', $seen);
        self::assertNotContains('2,-1', $seen);
        self::assertCount(17, $seen);
        self::assertCount(\count(array_unique($seen)), $seen);
    }

    public function testAFieldOfViewWithoutWallsIsTheArea(): void
    {
        self::assertCount(19, new Axial(0, 0)->fieldOfView(2, static fn (Axial $hex): bool => false));
        self::assertSame(['0,0'], $this->keys(new Axial(0, 0)->fieldOfView(0, static fn (Axial $hex): bool => true)));
    }

    public function testAFieldOfViewTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('The radius of a field of view');

        new Axial(0, 0)->fieldOfView(Limits::SIGHT + 1, static fn (Axial $hex): bool => false);
    }

    public function testOffsetsOfTheGenerator(): void
    {
        // The hex maps of tilttek/generator: odd rows pushed right, q = col - floor(row / 2).
        foreach ([[0, 0], [3, 1], [2, 4], [5, 7]] as [$col, $row]) {
            $offset = new Axial($col - intdiv($row, 2), $row)->toOffset(OffsetLayout::OddRows);
            self::assertSame(['col' => $col, 'row' => $row], $offset->toArray());
        }
    }

    public function testDirectionsCountRoundTheSix(): void
    {
        $origin = new Axial(0, 0);

        self::assertSame('1,-1', $origin->neighbor(7)->key());
        self::assertSame('0,1', $origin->neighbor(11)->key());
        self::assertSame('1,0', $origin->neighbor(12)->key());
        self::assertSame('1,-1', $origin->neighbor(13)->key());
        self::assertSame('1,-1', $origin->neighbor(-5)->key());
        self::assertSame('0,1', $origin->neighbor(-7)->key());
    }

    /**
     * @return iterable<string, array{Axial}>
     */
    public static function wallsWhoseKeysWouldCollide(): iterable
    {
        yield 'q 12, r 3' => [new Axial(12, 3)];
        yield 'q 1, r 23' => [new Axial(1, 23)];
    }

    #[DataProvider('wallsWhoseKeysWouldCollide')]
    public function testAFieldOfViewTellsHexagonsApartWhoseCoordinatesRunTogether(Axial $wall): void
    {
        $viewer = new Axial(0, 0);
        $seen = $this->keys($viewer->fieldOfView(25, static fn (Axial $hex): bool => $hex->equals($wall)));
        $expected = [$viewer->key()];
        foreach ($viewer->area(25) as $hex) {
            if ($hex->equals($viewer)) {
                continue;
            }

            $between = \array_slice($viewer->lineTo($hex), 1, -1);
            if ([] === array_filter($between, static fn (Axial $step): bool => $step->equals($wall))) {
                $expected[] = $hex->key();
            }
        }

        self::assertSame($expected, $seen);
    }

    public function testAFieldOfViewAsksOnceAboutEachHexagon(): void
    {
        $asked = [];
        new Axial(0, 0)->fieldOfView(4, static function (Axial $hex) use (&$asked): bool {
            $asked[] = $hex->key();

            return false;
        });

        self::assertNotSame([], $asked);
        self::assertSame($asked, array_values(array_unique($asked)));
    }

    public function testAFieldOfViewAsksNothingBeyondAWallOnTheSameLine(): void
    {
        $asked = [];
        new Axial(0, 0)->fieldOfView(3, static function (Axial $hex) use (&$asked): bool {
            $asked[] = $hex->key();

            return $hex->equals(new Axial(1, 0));
        });

        self::assertContains('1,0', $asked);
        self::assertNotContains('2,0', $asked);
    }
}
