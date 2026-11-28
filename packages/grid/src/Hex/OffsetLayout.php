<?php

declare(strict_types=1);

namespace Tilttek\Grid\Hex;

/**
 * Which rows or columns of a hex grid are pushed half a hexagon, for its offset coordinates.
 */
enum OffsetLayout: string
{
    /**
     * Pointy hexagons in rows, every odd row pushed half a hexagon to the right: the hex maps of `tilttek/generator`.
     */
    case OddRows = 'odd-r';

    /**
     * Pointy hexagons in rows, every even row pushed half a hexagon to the right.
     */
    case EvenRows = 'even-r';

    /**
     * Flat hexagons in columns, every odd column pushed half a hexagon down.
     */
    case OddColumns = 'odd-q';

    /**
     * Flat hexagons in columns, every even column pushed half a hexagon down.
     */
    case EvenColumns = 'even-q';

    public function orientation(): Orientation
    {
        return match ($this) {
            self::OddRows, self::EvenRows => Orientation::Pointy,
            self::OddColumns, self::EvenColumns => Orientation::Flat,
        };
    }
}
