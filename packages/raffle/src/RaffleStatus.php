<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

/**
 * Where a raffle stands.
 */
enum RaffleStatus: string
{
    /** It takes entries. */
    case Open = 'open';

    /** Its entries are closed and hashed, and its source of randomness is fixed: it waits for the draw. */
    case Sealed = 'sealed';

    /** Its winners are drawn and its proof is published. */
    case Drawn = 'drawn';
}
