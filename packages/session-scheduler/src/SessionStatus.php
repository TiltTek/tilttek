<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

/**
 * Where a session stands: planned, which a reschedule keeps, cancelled, or held.
 */
enum SessionStatus: string
{
    case Planned = 'planned';
    case Cancelled = 'cancelled';
    case Held = 'held';
}
