<?php

declare(strict_types=1);

namespace Tilttek\Generator\Definition;

/**
 * The shape of a grid map: square cells, or hexagons in rows where every odd row is pushed half a cell to the right.
 */
enum MapLayout: string
{
    case Square = 'square';
    case Hex = 'hex';
}
