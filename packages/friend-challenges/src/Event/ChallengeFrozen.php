<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

/**
 * Dispatched once a moderator freezes a challenge; its prize stays held.
 */
final readonly class ChallengeFrozen
{
    public function __construct(
        public string $challenge,
    ) {
    }
}
