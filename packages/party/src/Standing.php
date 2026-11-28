<?php

declare(strict_types=1);

namespace Tilttek\Party;

/**
 * Whether a member plays with the party now, or sits out for a while and keeps their place: an away member is left
 * out of the shares of the purse and of the composition, never removed.
 */
enum Standing: string
{
    case Active = 'active';

    case Away = 'away';
}
