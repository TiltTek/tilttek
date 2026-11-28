<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Square;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;
use Tilttek\Grid\Square\Cell;
use Tilttek\Grid\Square\DiagonalRule;
use Tilttek\Grid\Square\Movement;

#[CoversClass(Movement::class)]
#[CoversClass(DiagonalRule::class)]
final class MovementTest extends TestCase
{
    /**
     * @return iterable<string, array{DiagonalRule, int, int, int}>
     */
    public static function costs(): iterable
    {
        yield 'orthogonal' => [DiagonalRule::Orthogonal, 3, -2, 5];
        yield '5-5-5' => [DiagonalRule::Uniform, 3, -2, 3];
        yield '5-10-5, one diagonal' => [DiagonalRule::Alternating, 1, 1, 1];
        yield '5-10-5, two diagonals' => [DiagonalRule::Alternating, 2, -2, 3];
        yield '5-10-5, three diagonals' => [DiagonalRule::Alternating, -3, 3, 4];
        yield '5-10-5, mixed' => [DiagonalRule::Alternating, 5, 2, 6];
        yield '5-10-5, long side vertical' => [DiagonalRule::Alternating, 2, 5, 6];
        yield 'euclidean, exact' => [DiagonalRule::Euclidean, 3, 4, 5];
        yield 'euclidean, rounded' => [DiagonalRule::Euclidean, 1, 1, 1];
        yield 'euclidean, rounded up' => [DiagonalRule::Euclidean, 2, 2, 3];
    }

    #[DataProvider('costs')]
    public function testDiagonalRules(DiagonalRule $rule, int $dx, int $dy, int $expected): void
    {
        self::assertSame($expected, $rule->cells($dx, $dy));
        self::assertSame($expected, new Movement($rule)->distance(new Cell(10, 10), new Cell(10 + $dx, 10 + $dy)));
    }

    public function testTheEuclideanRuleTakesTheRoundingMode(): void
    {
        self::assertSame(2, DiagonalRule::Euclidean->cells(1, 1, RoundingMode::PositiveInfinity));
        self::assertSame(1, new Movement(DiagonalRule::Euclidean, RoundingMode::PositiveInfinity)->distance(new Cell(0, 0), new Cell(1, 0)));
        self::assertSame(2, new Movement(DiagonalRule::Euclidean, RoundingMode::PositiveInfinity)->distance(new Cell(0, 0), new Cell(1, 1)));
        self::assertSame(2, new Movement(DiagonalRule::Euclidean, RoundingMode::TowardsZero)->distance(new Cell(0, 0), new Cell(2, 2)));
    }

    public function testTheDefaultRuleIsUniform(): void
    {
        $movement = new Movement();

        self::assertSame(DiagonalRule::Uniform, $movement->rule);
        self::assertSame(RoundingMode::HalfAwayFromZero, $movement->rounding);
    }

    /**
     * @param list<Cell> $cells
     *
     * @return list<string>
     */
    private function keys(array $cells): array
    {
        return array_map(static fn (Cell $cell): string => $cell->key(), $cells);
    }

    public function testLines(): void
    {
        $movement = new Movement();

        self::assertSame(['0,0'], $this->keys($movement->line(new Cell(0, 0), new Cell(0, 0))));
        self::assertSame(['0,0', '1,0', '2,1', '3,1'], $this->keys($movement->line(new Cell(0, 0), new Cell(3, 1))));
        self::assertSame(['0,0', '1,1', '2,2'], $this->keys($movement->line(new Cell(0, 0), new Cell(2, 2))));
        self::assertSame(['3,1', '2,1', '1,0', '0,0'], $this->keys($movement->line(new Cell(3, 1), new Cell(0, 0))));
        self::assertSame(['-2,-2', '-2,-1', '-1,0', '-1,1'], $this->keys($movement->line(new Cell(-2, -2), new Cell(-1, 1))));
    }

    public function testATooLongLineIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);

        new Movement()->line(new Cell(0, 0), new Cell(Limits::LINE + 1, 0));
    }

    public function testAreasAndRings(): void
    {
        $movement = new Movement();

        self::assertSame(['5,5'], $this->keys($movement->area(new Cell(5, 5), 0)));
        self::assertCount(9, $movement->area(new Cell(5, 5), 1));
        self::assertCount(25, $movement->area(new Cell(5, 5), 2));
        self::assertSame(['4,4', '5,4', '6,4', '4,5', '6,5', '4,6', '5,6', '6,6'], $this->keys($movement->ring(new Cell(5, 5), 1)));
        self::assertSame(['5,5'], $this->keys($movement->ring(new Cell(5, 5), 0)));
        self::assertSame(['0,-1', '-1,0', '0,0', '1,0', '0,1'], $this->keys(new Movement(DiagonalRule::Orthogonal)->area(new Cell(0, 0), 1)));
        self::assertCount(21, new Movement(DiagonalRule::Alternating)->area(new Cell(0, 0), 2));
        self::assertCount(Limits::RADIUS * 8, $movement->ring(new Cell(0, 0), Limits::RADIUS));
    }

    public function testAnAreaTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('The radius of an area lies from 0 to 100; it is 101.');

        new Movement()->area(new Cell(0, 0), Limits::RADIUS + 1);
    }

    public function testARingTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('The radius of a ring lies from 0 to 100; it is 101.');

        new Movement()->ring(new Cell(0, 0), Limits::RADIUS + 1);
    }

    public function testAFieldOfViewStopsAtWalls(): void
    {
        $wall = new Cell(1, 0);
        $seen = new Movement()->fieldOfView(new Cell(0, 0), 2, static fn (Cell $cell): bool => $cell->equals($wall));
        $keys = $this->keys($seen);

        self::assertSame('0,0', $keys[0]);
        self::assertContains('1,0', $keys);
        self::assertNotContains('2,0', $keys);
        self::assertContains('2,1', $keys);
        self::assertContains('-2,0', $keys);
        self::assertNotContains('2,-1', $keys);
        self::assertCount(23, $seen);
        self::assertCount(\count(array_unique($keys)), $keys);
    }

    public function testAFieldOfViewWithoutWallsIsTheArea(): void
    {
        self::assertCount(25, new Movement()->fieldOfView(new Cell(0, 0), 2, static fn (Cell $cell): bool => false));
        self::assertSame(['0,0'], $this->keys(new Movement()->fieldOfView(new Cell(0, 0), 0, static fn (Cell $cell): bool => true)));
    }

    public function testAFieldOfViewTooLargeIsRefused(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('The radius of a field of view lies from 0 to 30; it is 31.');

        new Movement()->fieldOfView(new Cell(0, 0), Limits::SIGHT + 1, static fn (Cell $cell): bool => false);
    }

    /**
     * @return iterable<string, array{Cell}>
     */
    public static function wallsWhoseKeysWouldCollide(): iterable
    {
        yield 'x 12, y 3' => [new Cell(12, 3)];
        yield 'x 1, y 23' => [new Cell(1, 23)];
    }

    #[DataProvider('wallsWhoseKeysWouldCollide')]
    public function testAFieldOfViewTellsCellsApartWhoseCoordinatesRunTogether(Cell $wall): void
    {
        $movement = new Movement();
        $viewer = new Cell(0, 0);
        $seen = $this->keys($movement->fieldOfView($viewer, 25, static fn (Cell $cell): bool => $cell->equals($wall)));
        $expected = [$viewer->key()];
        foreach ($movement->area($viewer, 25) as $cell) {
            if ($cell->equals($viewer)) {
                continue;
            }

            $between = \array_slice($movement->line($viewer, $cell), 1, -1);
            if ([] === array_filter($between, static fn (Cell $step): bool => $step->equals($wall))) {
                $expected[] = $cell->key();
            }
        }

        self::assertSame($expected, $seen);
    }

    public function testAFieldOfViewAsksOnceAboutEachCell(): void
    {
        $asked = [];
        new Movement()->fieldOfView(new Cell(0, 0), 4, static function (Cell $cell) use (&$asked): bool {
            $asked[] = $cell->key();

            return false;
        });

        self::assertNotSame([], $asked);
        self::assertSame($asked, array_values(array_unique($asked)));
    }

    public function testAFieldOfViewAsksNothingBeyondAWallOnTheSameLine(): void
    {
        $asked = [];
        new Movement()->fieldOfView(new Cell(0, 0), 3, static function (Cell $cell) use (&$asked): bool {
            $asked[] = $cell->key();

            return $cell->equals(new Cell(1, 0));
        });

        self::assertContains('1,0', $asked);
        self::assertNotContains('2,0', $asked);
    }

    public function testALineThroughACornerBreaksTheTieTowardsTheNextCell(): void
    {
        $movement = new Movement();

        self::assertSame(['0,0', '0,-1', '-1,-2'], $this->keys($movement->line(new Cell(0, 0), new Cell(-1, -2))));
        self::assertSame(['0,0', '-1,0', '-2,-1'], $this->keys($movement->line(new Cell(0, 0), new Cell(-2, -1))));
    }
}
