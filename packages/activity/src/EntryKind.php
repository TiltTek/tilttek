<?php

declare(strict_types=1);

namespace Tilttek\Activity;

/**
 * What an entry of the journal says: an activity was recorded, or an activity recorded earlier was cancelled — an
 * order refunded, a comment removed. Nothing is ever rewritten: a cancellation is an entry of its own, which a reader
 * replays as the reverse of the activity.
 */
enum EntryKind: string
{
    case Recorded = 'recorded';

    case Cancelled = 'cancelled';
}
