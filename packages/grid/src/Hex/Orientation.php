<?php

declare(strict_types=1);

namespace Tilttek\Grid\Hex;

/**
 * How the hexagons of a grid stand: a point up, in rows, or a flat side up, in columns.
 */
enum Orientation: string
{
    case Pointy = 'pointy';
    case Flat = 'flat';
}
