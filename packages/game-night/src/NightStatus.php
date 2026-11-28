<?php

declare(strict_types=1);

namespace Tilttek\GameNight;

/**
 * Where a night stands: planned, open to sign-ups, or cancelled for good.
 */
enum NightStatus: string
{
    /** Planned by its host, not open to sign-ups yet. */
    case Draft = 'draft';

    /** Open to sign-ups, votes and plays. */
    case Published = 'published';

    /** Cancelled: nothing changes any more. */
    case Cancelled = 'cancelled';
}
