<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once a member takes part in a challenge.
 */
final readonly class ParticipantJoined
{
    public function __construct(
        public string $challenge,
        public ResourceReference $member,
    ) {
    }
}
