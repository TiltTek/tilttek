<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Elimination;

/**
 * What fills a place of a bracket.
 */
enum SlotKind: string
{
    /** A participant known when the bracket is made. */
    case Participant = 'participant';
    /** A qualifier of an earlier stage or pool, by rank, known when that stage ends. */
    case Qualifier = 'qualifier';
    /** The winner of an earlier match of the bracket. */
    case WinnerOf = 'winner-of';
    /** No one: the participant of the other place goes through. */
    case Bye = 'bye';
}
