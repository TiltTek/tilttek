<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Statistics;

/**
 * Where the rolls come from, which changes what an honest explanation says of them.
 */
enum Origin: string
{
    /** A die thrown at the table, whose rolls a player wrote down: its shape and its balance may matter. */
    case Physical = 'physical';

    /** Rolls drawn by software from a random generator that serves every die alike: no digital die is luckier. */
    case Digital = 'digital';
}
