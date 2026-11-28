<?php

declare(strict_types=1);

namespace Tilttek\TableRules;

/**
 * Where a player stands with the current rules of a table.
 */
enum AcceptanceState: string
{
    /** The player has not answered the rules that hold, or there are none yet. */
    case Pending = 'pending';

    /** The player accepted the rules that hold. */
    case Accepted = 'accepted';

    /** The player accepted an earlier version, and the rules changed since in a way that asks every player again. */
    case Outdated = 'outdated';

    /** The player declined the rules that hold. */
    case Declined = 'declined';
}
