<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

/**
 * What a guess gave.
 */
enum GuessOutcome: string
{
    /** The answer: the puzzle is solved. */
    case Correct = 'correct';

    /** A step on the way: the puzzle stays open, and its message says to keep going. */
    case Partial = 'partial';

    /** Neither the answer nor a step. */
    case Wrong = 'wrong';

    /** Too many guesses in the period: the guess was not checked. */
    case Throttled = 'throttled';
}
