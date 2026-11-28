<?php

declare(strict_types=1);

namespace Tilttek\Grid\Hex;

use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Limits;

/**
 * The offset coordinates of a hexagon — its column and its row, as a printed hex map numbers them — read under an
 * {@see OffsetLayout}. The same keys as the hex maps of `tilttek/generator`.
 */
final readonly class Offset
{
    /**
     * @throws OutOfBounds when a coordinate is farther than {@see Limits::COORDINATE} from zero
     */
    public function __construct(public int $col, public int $row)
    {
        Limits::coordinate($col, 'col');
        Limits::coordinate($row, 'row');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidGrid when the array is not `['col' => int, 'row' => int]`
     * @throws OutOfBounds when a coordinate is out of bounds
     */
    public static function fromArray(array $data): self
    {
        $col = $data['col'] ?? null;
        $row = $data['row'] ?? null;
        if (!\is_int($col) || !\is_int($row) || 2 !== \count($data)) {
            throw InvalidGrid::because('Offset coordinates are written as {"col": int, "row": int}.');
        }

        return new self($col, $row);
    }

    /**
     * @return array{col: int, row: int}
     */
    public function toArray(): array
    {
        return ['col' => $this->col, 'row' => $this->row];
    }

    public function equals(self $other): bool
    {
        return $this->col === $other->col && $this->row === $other->row;
    }

    /**
     * @throws OutOfBounds when the result is out of bounds
     */
    public function toAxial(OffsetLayout $layout): Axial
    {
        return match ($layout) {
            OffsetLayout::OddRows => new Axial($this->col - intdiv($this->row - ($this->row & 1), 2), $this->row),
            OffsetLayout::EvenRows => new Axial($this->col - intdiv($this->row + ($this->row & 1), 2), $this->row),
            OffsetLayout::OddColumns => new Axial($this->col, $this->row - intdiv($this->col - ($this->col & 1), 2)),
            OffsetLayout::EvenColumns => new Axial($this->col, $this->row - intdiv($this->col + ($this->col & 1), 2)),
        };
    }
}
