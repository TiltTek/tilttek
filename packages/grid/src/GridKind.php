<?php

declare(strict_types=1);

namespace Tilttek\Grid;

/**
 * The grid a map is drawn with, as an application stores it beside the map.
 */
enum GridKind: string
{
    case Square = 'square';
    case HexPointy = 'hex-pointy';
    case HexFlat = 'hex-flat';
    case Isometric = 'isometric';
    case None = 'none';
}
