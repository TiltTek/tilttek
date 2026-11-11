<?php

declare(strict_types=1);

namespace Tilttek\Quests;

/**
 * The genre of a quest, which says whose activity advances it and who accomplishes it:
 *
 * - `Personal`: a member's own activity, for themselves;
 * - `Community`: a challenge of the whole community, a collective goal everyone's activity advances — *a thousand games
 *   this month* —, accomplished once for all who took part;
 * - `Team`: the members who joined it under the same team, towards a goal they share;
 * - `Duel`: a challenge one member sends another, a race to accomplish it first once it is accepted.
 */
enum QuestKind: string
{
    case Personal = 'personal';
    case Community = 'community';
    case Team = 'team';
    case Duel = 'duel';
}
