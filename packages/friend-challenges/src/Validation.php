<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

/**
 * Who decides whether a proof meets the challenge: its creator, a vote of the creator and the other participants, or a
 * moderator of the site.
 */
enum Validation: string
{
    case Creator = 'creator';
    case Vote = 'vote';
    case Moderator = 'moderator';
}
