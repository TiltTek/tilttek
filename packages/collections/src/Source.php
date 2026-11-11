<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * How a copy came to its first member: on arrival with the base sets, as the item of the day, from a pack, as the
 * reward of a quest, a badge or an exchange of points, bought, received as a gift, or given by the site for any other
 * reason. A trade changes the owner of a copy, never its source.
 */
enum Source: string
{
    case Welcome = 'welcome';
    case Daily = 'daily';
    case Pack = 'pack';
    case Reward = 'reward';
    case Exchange = 'exchange';
    case Purchase = 'purchase';
    case Gift = 'gift';
    case Granted = 'granted';
}
