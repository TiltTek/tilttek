<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Application;

/**
 * Where a draw stands when its unit is printed.
 */
enum PrintState: string
{
    /** Revealed, and its proof checks out against the commitment shown before the payment. */
    case Verified = 'verified';

    /** Revealed, but its proof does not check out against the commitment shown. */
    case NotVerified = 'not-verified';

    /** Physical dice entered at a register, never committed. */
    case Physical = 'physical';

    /** Committed and not drawn yet: nothing to print. */
    case Pending = 'pending';
}
