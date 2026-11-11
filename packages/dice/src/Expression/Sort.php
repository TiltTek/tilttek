<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * The order the dice are listed in: ascending (`sa`, or `s` alone) or descending (`sd`) by value.
 */
enum Sort: string
{
    case Ascending = 'sa';
    case Descending = 'sd';
}
