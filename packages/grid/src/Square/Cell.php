<?php

declare(strict_types=1);

namespace Tilttek\Grid\Square;

use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;

/**
 * A cell of a square or isometric grid: its column x, to the right, and its row y, down. The same keys as the square
 * maps of `tilttek/generator`.
 */
final readonly class Cell
{
    /**
     * The four orthogonal steps, clockwise from the east.
     */
    private const array ORTHOGONAL = [[1, 0], [0, 1], [-1, 0], [0, -1]];

    /**
     * The four diagonal steps, clockwise from the south-east.
     */
    private const array DIAGONAL = [[1, 1], [-1, 1], [-1, -1], [1, -1]];

    /**
     * @throws OutOfBounds when a coordinate is farther than {@see Limits::COORDINATE} from zero
     */
    public function __construct(public int $x, public int $y)
    {
        Limits::coordinate($x, 'x');
        Limits::coordinate($y, 'y');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidGrid when the array is not `['x' => int, 'y' => int]`
     * @throws OutOfBounds when a coordinate is out of bounds
     */
    public static function fromArray(array $data): self
    {
        $x = $data['x'] ?? null;
        $y = $data['y'] ?? null;
        if (!\is_int($x) || !\is_int($y) || 2 !== \count($data)) {
            throw InvalidGrid::because('A square cell is written as {"x": int, "y": int}.');
        }

        return new self($x, $y);
    }

    /**
     * @return array{x: int, y: int}
     */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }

    public function equals(self $other): bool
    {
        return $this->x === $other->x && $this->y === $other->y;
    }

    /**
     * A key that names the cell, to index cells in an array.
     */
    public function key(): string
    {
        return $this->x.','.$this->y;
    }

    /**
     * @throws OutOfBounds when the result is out of bounds
     */
    public function plus(int $dx, int $dy): self
    {
        return new self($this->x + $dx, $this->y + $dy);
    }

    /**
     * The four cells that share a side with this one, clockwise from the east, then the four that share a corner,
     * clockwise from the south-east, when asked.
     *
     * @return list<self>
     *
     * @throws OutOfBounds when a neighbour is out of bounds
     */
    public function neighbors(bool $diagonals = true): array
    {
        $steps = $diagonals ? [...self::ORTHOGONAL, ...self::DIAGONAL] : self::ORTHOGONAL;

        return array_map(fn (array $step): self => $this->plus($step[0], $step[1]), $steps);
    }

    /**
     * This cell turned around a pivot by quarter turns, clockwise as the map is seen; a negative number turns it
     * counterclockwise. Turning every cell of a map piece around its reference cell turns the piece.
     *
     * @throws OutOfBounds when the result is out of bounds
     */
    public function rotateAround(self $pivot, int $quarterTurns): self
    {
        $dx = $this->x - $pivot->x;
        $dy = $this->y - $pivot->y;
        $turns = (($quarterTurns % 4) + 4) % 4;
        for ($turn = 0; $turn < $turns; ++$turn) {
            [$dx, $dy] = [-$dy, $dx];
        }

        return $pivot->plus($dx, $dy);
    }
}
