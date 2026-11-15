<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

use Tilttek\FriendChallenges\ChallengeState;

/**
 * Dispatched once a challenge ends without a winner — cancelled by its creator, expired, or removed by a moderator —,
 * after its prize is given back to its creator.
 */
final readonly class ChallengeEnded
{
    public function __construct(
        public string $challenge,
        public ChallengeState $state,
    ) {
    }
}
