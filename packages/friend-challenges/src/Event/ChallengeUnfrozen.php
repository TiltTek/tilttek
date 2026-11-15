<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

/**
 * Dispatched once a moderator unfreezes a challenge.
 */
final readonly class ChallengeUnfrozen
{
    public function __construct(
        public string $challenge,
    ) {
    }
}
