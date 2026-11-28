<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Event;

/**
 * A table was rolled, or a result of physical dice entered: an application sends a message from it — the table, the
 * row and the numbers, nothing else — and reads the rest from its catalogue.
 */
final readonly class TableRolled
{
    /**
     * @param string $table    the key of the table
     * @param int    $position the position of the row found, from 0, in the table's `$rows`
     * @param int    $total    the value looked up: the die and the modifier
     * @param bool   $physical whether the die was rolled at the table and entered, rather than rolled by the library
     */
    public function __construct(
        public string $table,
        public int $position,
        public int $total,
        public bool $physical,
    ) {
    }
}
