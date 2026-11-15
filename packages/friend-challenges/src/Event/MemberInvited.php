<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once for each friend invited to a challenge, when it is created: the application tells them once, and
 * never reminds them.
 */
final readonly class MemberInvited
{
    public function __construct(
        public string $challenge,
        public ResourceReference $member,
    ) {
    }
}
