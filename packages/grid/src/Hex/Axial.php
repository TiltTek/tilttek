<?php

declare(strict_types=1);

namespace Tilttek\Grid\Hex;

use Closure;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;

/**
 * A hexagon by its axial coordinates q and r — the cube coordinates without their third axis, s = -q - r —, the form
 * every computation of the library takes. The same keys as the hex maps of `tilttek/generator`.
 */
final readonly class Axial
{
    /**
     * The six steps to the neighbours, counterclockwise from the east (pointy) or the south-east (flat): the direction
     * numbers 0 to 5.
     */
    private const array DIRECTIONS = [[1, 0], [1, -1], [0, -1], [-1, 0], [-1, 1], [0, 1]];

    /**
     * The six steps to the hexagons that share no side but lie across a corner, in the same order.
     */
    private const array DIAGONALS = [[2, -1], [1, -2], [-1, -1], [-2, 1], [-1, 2], [1, 1]];

    /**
     * The scale of the nudge that settles a line passing exactly between two hexagons, the same way every time.
     */
    private const int NUDGE = 10;

    /**
     * @throws OutOfBounds when q, r or s is farther than {@see Limits::COORDINATE} from zero
     */
    public function __construct(public int $q, public int $r)
    {
        Limits::coordinate($q, 'q');
        Limits::coordinate($r, 'r');
        Limits::coordinate(-$q - $r, 's');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidGrid when the array is not `['q' => int, 'r' => int]`
     * @throws OutOfBounds when a coordinate is out of bounds
     */
    public static function fromArray(array $data): self
    {
        $q = $data['q'] ?? null;
        $r = $data['r'] ?? null;
        if (!\is_int($q) || !\is_int($r) || 2 !== \count($data)) {
            throw InvalidGrid::because('Axial coordinates are written as {"q": int, "r": int}.');
        }

        return new self($q, $r);
    }

    /**
     * @return array{q: int, r: int}
     */
    public function toArray(): array
    {
        return ['q' => $this->q, 'r' => $this->r];
    }

    public function s(): int
    {
        return -$this->q - $this->r;
    }

    public function equals(self $other): bool
    {
        return $this->q === $other->q && $this->r === $other->r;
    }

    /**
     * A key that names the hexagon, to index hexagons in an array.
     */
    public function key(): string
    {
        return $this->q.','.$this->r;
    }

    public function toCube(): Cube
    {
        return new Cube($this->q, $this->r, $this->s());
    }

    /**
     * @throws OutOfBounds when the result is out of bounds
     */
    public function toOffset(OffsetLayout $layout): Offset
    {
        return match ($layout) {
            OffsetLayout::OddRows => new Offset($this->q + intdiv($this->r - ($this->r & 1), 2), $this->r),
            OffsetLayout::EvenRows => new Offset($this->q + intdiv($this->r + ($this->r & 1), 2), $this->r),
            OffsetLayout::OddColumns => new Offset($this->q, $this->r + intdiv($this->q - ($this->q & 1), 2)),
            OffsetLayout::EvenColumns => new Offset($this->q, $this->r + intdiv($this->q + ($this->q & 1), 2)),
        };
    }

    /**
     * @throws OutOfBounds when the result is out of bounds
     */
    public function plus(self $other): self
    {
        return new self($this->q + $other->q, $this->r + $other->r);
    }

    /**
     * @throws OutOfBounds when the result is out of bounds
     */
    public function minus(self $other): self
    {
        return new self($this->q - $other->q, $this->r - $other->r);
    }

    /**
     * @throws OutOfBounds when the result is out of bounds
     */
    public function scale(int $factor): self
    {
        return new self($this->q * $factor, $this->r * $factor);
    }

    /**
     * The number of steps between this hexagon and another.
     */
    public function distanceTo(self $other): int
    {
        return intdiv(abs($this->q - $other->q) + abs($this->r - $other->r) + abs($this->s() - $other->s()), 2);
    }

    /**
     * The neighbour in a direction, 0 to 5; any other number counts round the six.
     *
     * @throws OutOfBounds when the result is out of bounds
     */
    public function neighbor(int $direction): self
    {
        $step = self::DIRECTIONS[(($direction % 6) + 6) % 6];

        return new self($this->q + $step[0], $this->r + $step[1]);
    }

    /**
     * The six hexagons that share a side with this one, in the order of the directions.
     *
     * @return list<self>
     *
     * @throws OutOfBounds when a neighbour is out of bounds
     */
    public function neighbors(): array
    {
        return array_map(fn (array $step): self => new self($this->q + $step[0], $this->r + $step[1]), self::DIRECTIONS);
    }

    /**
     * The six hexagons two steps away across a corner, in the order of the directions.
     *
     * @return list<self>
     *
     * @throws OutOfBounds when a hexagon is out of bounds
     */
    public function diagonals(): array
    {
        return array_map(fn (array $step): self => new self($this->q + $step[0], $this->r + $step[1]), self::DIAGONALS);
    }

    /**
     * This hexagon turned around a pivot by sixths of a turn, clockwise as the map is seen; a negative number turns it
     * counterclockwise. Turning every hexagon of a map piece around its reference hexagon turns the piece.
     *
     * @throws OutOfBounds when the result is out of bounds
     */
    public function rotateAround(self $pivot, int $sixthTurns): self
    {
        $q = $this->q - $pivot->q;
        $r = $this->r - $pivot->r;
        $turns = (($sixthTurns % 6) + 6) % 6;
        for ($turn = 0; $turn < $turns; ++$turn) {
            [$q, $r] = [-$r, $q + $r];
        }

        return new self($pivot->q + $q, $pivot->r + $r);
    }

    /**
     * The hexagons a straight line from the centre of this hexagon to the centre of another crosses, both ends
     * included, each one a neighbour of the one before.
     *
     * @return non-empty-list<self>
     *
     * @throws OutOfBounds when the line has more than {@see Limits::LINE} steps
     */
    public function lineTo(self $other): array
    {
        $hexes = [$this];
        foreach ($this->walk($other) as [$q, $r]) {
            $hexes[] = new self($q, $r);
        }

        return $hexes;
    }

    /**
     * Every hexagon at most a radius away — the area of an effect described by its distance in hexagons —, column by
     * column.
     *
     * @return list<self>
     *
     * @throws OutOfBounds when the radius is negative or larger than {@see Limits::RADIUS}
     */
    public function area(int $radius): array
    {
        Limits::radius($radius, Limits::RADIUS, 'an area');
        $hexes = [];
        for ($q = -$radius; $q <= $radius; ++$q) {
            for ($r = max(-$radius, -$q - $radius); $r <= min($radius, -$q + $radius); ++$r) {
                $hexes[] = new self($this->q + $q, $this->r + $r);
            }
        }

        return $hexes;
    }

    /**
     * Every hexagon exactly a radius away, walking round from the one in direction 4.
     *
     * @return list<self>
     *
     * @throws OutOfBounds when the radius is negative or larger than {@see Limits::RADIUS}
     */
    public function ring(int $radius): array
    {
        Limits::radius($radius, Limits::RADIUS, 'a ring');
        if (0 === $radius) {
            return [$this];
        }

        $hex = $this->plus(new self(0, 0)->neighbor(4)->scale($radius));
        $hexes = [];
        for ($side = 0; $side < 6; ++$side) {
            for ($step = 0; $step < $radius; ++$step) {
                $hexes[] = $hex;
                $hex = $hex->neighbor($side);
            }
        }

        return $hexes;
    }

    /**
     * Every hexagon of an area a viewer here can see: a hexagon is seen when no hexagon strictly between it and the
     * viewer, on the line between their centres, blocks the sight. A hexagon that blocks is seen itself.
     *
     * @param Closure(self): bool $blocksSight whether a hexagon blocks the sight
     *
     * @return non-empty-list<self>
     *
     * @throws OutOfBounds when the radius is negative or larger than {@see Limits::SIGHT}
     */
    public function fieldOfView(int $radius, Closure $blocksSight): array
    {
        Limits::radius($radius, Limits::SIGHT, 'a field of view');
        $seen = [$this];
        // Each hexagon is asked once whether it blocks, however many lines cross it.
        $blocks = [];
        foreach ($this->area($radius) as $hex) {
            if ($hex->equals($this)) {
                continue;
            }

            $steps = $this->walk($hex);
            array_pop($steps);
            $clear = true;
            foreach ($steps as [$q, $r]) {
                $key = $q.','.$r;
                if ($blocks[$key] ??= $blocksSight(new self($q, $r))) {
                    $clear = false;
                    break;
                }
            }

            if ($clear) {
                $seen[] = $hex;
            }
        }

        return $seen;
    }

    /**
     * The coordinates of the hexagons of the line to another, this one left out, computed with integers only.
     *
     * @return list<array{int, int}>
     *
     * @throws OutOfBounds when the line has more than {@see Limits::LINE} steps
     */
    private function walk(self $other): array
    {
        $steps = Limits::line($this->distanceTo($other));
        $dq = $other->q - $this->q;
        $dr = $other->r - $this->r;
        $ds = $other->s() - $this->s();
        $denominator = $steps * self::NUDGE;
        $hexes = [];
        for ($step = 1; $step <= $steps; ++$step) {
            [$q, $r] = Cube::round(
                ($this->q * $steps + $dq * $step) * self::NUDGE + 1,
                ($this->r * $steps + $dr * $step) * self::NUDGE + 2,
                ($this->s() * $steps + $ds * $step) * self::NUDGE - 3,
                $denominator,
            );
            $hexes[] = [$q, $r];
        }

        return $hexes;
    }
}
