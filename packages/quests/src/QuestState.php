<?php

declare(strict_types=1);

namespace Tilttek\Quests;

/**
 * Where a quest stands for a member:
 *
 * - `Closed`: not open now, and not accomplished in its last occurrence;
 * - `Locked`: open, but the quest it follows is not accomplished yet;
 * - `Available`: open, waiting for the member to accept it — or to join it under a team;
 * - `UnderWay`: followed, its steps advancing;
 * - `Abandoned`: the member gave it up;
 * - `Completed`: accomplished in its current occurrence — or in its last one, once closed.
 */
enum QuestState: string
{
    case Closed = 'closed';
    case Locked = 'locked';
    case Available = 'available';
    case UnderWay = 'under_way';
    case Abandoned = 'abandoned';
    case Completed = 'completed';
}
