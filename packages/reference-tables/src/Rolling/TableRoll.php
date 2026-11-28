<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Rolling;

use Tilttek\Dice\Result\PoolResult;
use Tilttek\ReferenceTables\Row;

/**
 * A roll on a table: the die's value, the modifier, the total looked up, and the row found — with every die kept by
 * `tilttek/dice` when the library rolled them.
 */
final readonly class TableRoll
{
    /**
     * @param string      $table    the key of the table
     * @param int         $position the position of the row, from 0, in the table's `$rows`
     * @param int         $natural  what the die gave
     * @param int         $total    the value looked up: the die and the modifier
     * @param bool        $clamped  whether the total fell beyond the table and took its first or last row
     * @param ?PoolResult $dice     the roll of `tilttek/dice`; null when the die was rolled at the table and entered
     */
    public function __construct(
        public string $table,
        public Row $row,
        public int $position,
        public int $natural,
        public int $modifier,
        public int $total,
        public bool $clamped,
        public ?PoolResult $dice,
    ) {
    }

    /**
     * Whether the die was rolled at the table and its value entered.
     */
    public function isPhysical(): bool
    {
        return !$this->dice instanceof PoolResult;
    }
}
