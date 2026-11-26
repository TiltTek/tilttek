<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

/**
 * What happened to a guild or one of its members.
 */
enum Change: string
{
    case Founded = 'founded';

    case Joined = 'joined';

    case Requested = 'requested';

    case Withdrawn = 'withdrawn';

    case Approved = 'approved';

    case Declined = 'declined';

    case Left = 'left';

    case Removed = 'removed';

    case Promoted = 'promoted';

    case Demoted = 'demoted';

    case Transferred = 'transferred';

    case Updated = 'updated';

    case Dissolved = 'dissolved';
}
