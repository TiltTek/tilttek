<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

/**
 * Where a puzzle stands in a game.
 */
enum PuzzleStatus: string
{
    /** Not open yet: its title and hints are not shown, and guesses are refused. */
    case Locked = 'locked';

    /** Open: it takes guesses and reveals its hints. */
    case Open = 'open';

    /** Solved, by its answer or skipped by the host. */
    case Solved = 'solved';
}
