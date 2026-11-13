<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * The shape of a card: `size` rows of `size` cells, numbered row by row from 0, and, on an odd size, a free centre
 * that is ticked from the start.
 */
final readonly class Grid
{
    public const int SMALLEST = 3;

    public const int LARGEST = 9;

    /** @var int<3, 9> */
    public int $size;

    public function __construct(
        int $size = 5,
        public bool $freeCentre = true,
    ) {
        if ($size < self::SMALLEST || $size > self::LARGEST) {
            throw InvalidBingo::because(\sprintf('A card is %d to %d cells wide; %d is not allowed.', self::SMALLEST, self::LARGEST, $size));
        }

        if ($freeCentre && 0 === $size % 2) {
            throw InvalidBingo::because(\sprintf('A card %d cells wide has no centre to leave free.', $size));
        }

        $this->size = $size;
    }

    /**
     * @return int<9, 81>
     */
    public function cells(): int
    {
        return $this->size * $this->size;
    }

    /** The cell left free, or null. */
    public function centre(): ?int
    {
        return $this->freeCentre ? intdiv($this->cells(), 2) : null;
    }

    /**
     * How many squares a card draws from the pool: every cell but the free centre.
     *
     * @return int<8, 81>
     */
    public function squares(): int
    {
        return $this->cells() - ($this->freeCentre ? 1 : 0);
    }

    public function position(int $row, int $column): int
    {
        if ($row < 0 || $row >= $this->size || $column < 0 || $column >= $this->size) {
            throw InvalidBingo::because(\sprintf('A card %d cells wide has no cell at row %d, column %d.', $this->size, $row, $column));
        }

        return $row * $this->size + $column;
    }
}
