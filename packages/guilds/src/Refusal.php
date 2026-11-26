<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

/**
 * Why the library refuses an act on a guild or a goal.
 */
enum Refusal: string
{
    case AlreadyFounded = 'already_founded';

    case AlreadyMember = 'already_member';

    case AlreadyRequested = 'already_requested';

    case NotMember = 'not_member';

    case NoRequest = 'no_request';

    case Full = 'full';

    case TooManyGuilds = 'too_many_guilds';

    case TooManyRequests = 'too_many_requests';

    case NotAllowed = 'not_allowed';

    case FounderStays = 'founder_stays';

    case BelowMembers = 'below_members';

    case GoalExists = 'goal_exists';

    case TooManyGoals = 'too_many_goals';

    case GoalOver = 'goal_over';
}
