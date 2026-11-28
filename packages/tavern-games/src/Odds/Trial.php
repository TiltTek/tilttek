<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Odds;

/**
 * How one simulated ending went for the seat it is told to.
 */
enum Trial: string
{
    case Win = 'win';
    case Tie = 'tie';
    case Loss = 'loss';
}
