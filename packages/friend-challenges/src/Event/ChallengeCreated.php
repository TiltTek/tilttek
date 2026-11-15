<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once a challenge is created and its prize held.
 */
final readonly class ChallengeCreated
{
    public function __construct(
        public string $challenge,
        public ResourceReference $creator,
    ) {
    }
}
