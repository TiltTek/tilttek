<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Beacon;

/**
 * What one relay of a beacon gave to one read.
 */
enum RelayOutcome: string
{
    /** Its answer checked and gave the signature the read kept, or would have kept with a quorum. */
    case Agreed = 'agreed';

    /** Its answer checked, but its signature is not the one most relays gave. */
    case Disagreed = 'disagreed';

    /** Its answer came whole but does not check: malformed, another round, another chain or another scheme. */
    case Invalid = 'invalid';

    /** It answered with a status other than `200`: the round is not published yet, a redirect, an error. */
    case Status = 'status';

    /** Its answer is longer than an answer of the beacon may be. */
    case TooLarge = 'too-large';

    /** It stayed silent past the timeout, or the read ran out of time before its answer ended. */
    case TimedOut = 'timed-out';

    /** It could not be reached, or its answer broke on the way. */
    case Unreachable = 'unreachable';

    /** Its answer ended before it was whole. */
    case Incomplete = 'incomplete';

    /**
     * Whether the relay gave an answer that checks, the same or not as the others.
     */
    public function answered(): bool
    {
        return self::Agreed === $this || self::Disagreed === $this;
    }
}
