<?php

declare(strict_types=1);

namespace Tilttek\TurnClock;

/**
 * What happens when a player's budget runs out.
 */
enum Overtime: string
{
    /** The budget goes below zero, and the time over is counted: the table decides. */
    case Continue = 'continue';

    /** The player is out of time: their budget stays at zero, and their turns are skipped. */
    case Forfeit = 'forfeit';
}
