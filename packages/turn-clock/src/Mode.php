<?php

declare(strict_types=1);

namespace Tilttek\TurnClock;

/**
 * How the increment of a time control gives time back, and whether the budget carries from one turn to the next.
 */
enum Mode: string
{
    /** The increment is added to the budget after each turn (Fischer). */
    case Fischer = 'fischer';

    /** The time spent in a turn is given back after it, up to the increment (Bronstein). */
    case Bronstein = 'bronstein';

    /** The budget runs only once the increment has elapsed in each turn (a simple delay). */
    case Delay = 'delay';

    /** Each turn has the whole budget again, nothing carried over: days per move, as in a game by correspondence. */
    case PerTurn = 'per-turn';
}
