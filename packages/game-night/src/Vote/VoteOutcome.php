<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Vote;

/**
 * How a vote ended.
 */
enum VoteOutcome: string
{
    /** Nobody voted. */
    case NoVotes = 'no_votes';

    /** One game has more votes than every other. */
    case Winner = 'winner';

    /** Several games share the most votes: the host decides between them, the library does not draw. */
    case Tie = 'tie';
}
