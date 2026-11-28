<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

/**
 * Where an entry of the inbox stands: waiting to be shown, seen by the member, withdrawn because the reward was taken
 * back, or lapsed — its moment to be shown passed, or newer entries pushed it out — while the reward stands.
 */
enum EntryState: string
{
    case Pending = 'pending';

    case Seen = 'seen';

    case Withdrawn = 'withdrawn';

    case Lapsed = 'lapsed';
}
