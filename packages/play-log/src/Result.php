<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

/**
 * How a play ended: who won is told by the seats of a competitive play, by the result of a cooperative one, and no one
 * won a play left unfinished.
 */
enum Result: string
{
    /** The players played against each other: the seats marked as winners won, none or several. */
    case Competitive = 'competitive';

    /** The players played together and won: every seat won. */
    case CooperativeWin = 'cooperative-win';

    /** The players played together and lost: no seat won. */
    case CooperativeLoss = 'cooperative-loss';

    /** The play stopped before its end: no seat won. */
    case Unfinished = 'unfinished';

    public function isCooperative(): bool
    {
        return self::CooperativeWin === $this || self::CooperativeLoss === $this;
    }
}
