<?php

declare(strict_types=1);

namespace Tilttek\Quests;

/**
 * How a member comes to follow a quest: `Automatic`, every member follows it as soon as it is open, or
 * `OnAcceptance`, only the activity after the member accepted it counts. A team quest and a duel are always joined by
 * acceptance.
 */
enum Admission: string
{
    case Automatic = 'automatic';
    case OnAcceptance = 'accept';
}
