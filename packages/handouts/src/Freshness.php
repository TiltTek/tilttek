<?php

declare(strict_types=1);

namespace Tilttek\Handouts;

/**
 * What a player has seen of a handout revealed to them: nothing yet, an earlier revision, or the current one.
 */
enum Freshness: string
{
    case Unseen = 'unseen';

    case Outdated = 'outdated';

    case Seen = 'seen';
}
