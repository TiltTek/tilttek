<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

/**
 * Who may take part: the friends the creator invited, or any member. A challenge is between friends unless its
 * creator chooses to open it.
 */
enum Visibility: string
{
    case Friends = 'friends';
    case Open = 'open';
}
