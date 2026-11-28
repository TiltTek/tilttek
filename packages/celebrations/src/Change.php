<?php

declare(strict_types=1);

namespace Tilttek\Celebrations;

/**
 * What happened in a member's inbox.
 */
enum Change: string
{
    case Received = 'received';

    case Seen = 'seen';

    case Withdrawn = 'withdrawn';

    case Lapsed = 'lapsed';

    case PreferencesChanged = 'preferences_changed';

    case Forgotten = 'forgotten';
}
