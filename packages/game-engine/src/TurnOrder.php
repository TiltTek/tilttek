<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * Who acts during a phase.
 */
enum TurnOrder: string
{
    /** Anyone acts at any time: there are no turns, an exploration scene. */
    case Free = 'free';

    /** One seat after the other, in the order of the seats, round after round. */
    case Sequential = 'sequential';

    /** One seat after the other, in the order of their initiative, the highest first, round after round. */
    case Initiative = 'initiative';

    /** Every seat acts once in a round, in any order; the round ends when all have ended their turn. */
    case Simultaneous = 'simultaneous';
}
