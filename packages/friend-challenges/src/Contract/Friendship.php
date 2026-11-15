<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Contract;

use Phil\Resource\ResourceReference;

/**
 * Who is a friend of whom, as the site keeps it: a member invites only their friends to a challenge.
 */
interface Friendship
{
    public function areFriends(ResourceReference $member, ResourceReference $other): bool;
}
