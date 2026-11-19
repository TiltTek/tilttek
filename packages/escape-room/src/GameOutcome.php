<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

/**
 * How a game ended.
 */
enum GameOutcome: string
{
    /** The finale solved in time. */
    case Escaped = 'escaped';

    /** The countdown reached zero first. */
    case TimeUp = 'time-up';

    /** Ended by the host before either. */
    case Abandoned = 'abandoned';
}
