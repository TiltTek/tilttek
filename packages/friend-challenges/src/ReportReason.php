<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

/**
 * Why a member reports a challenge or a proof to the moderators of the site, chosen from a list.
 */
enum ReportReason: string
{
    case Spam = 'spam';
    case Harassment = 'harassment';
    case Cheating = 'cheating';
    case Unsafe = 'unsafe';
    case Inappropriate = 'inappropriate';
    case Other = 'other';
}
