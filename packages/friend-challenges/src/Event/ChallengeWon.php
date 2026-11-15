<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once a challenge is won, after its prize is paid to the winner: the moment the application celebrates.
 */
final readonly class ChallengeWon
{
    public function __construct(
        public string $challenge,
        public ResourceReference $winner,
        public string $proof,
        public int $prize,
        public string $unit,
    ) {
    }
}
