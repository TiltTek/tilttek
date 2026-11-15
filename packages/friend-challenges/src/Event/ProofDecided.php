<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

use Tilttek\FriendChallenges\ProofState;

/**
 * Dispatched once a proof is accepted, rejected or removed by a moderator.
 */
final readonly class ProofDecided
{
    public function __construct(
        public string $challenge,
        public string $proof,
        public ProofState $state,
    ) {
    }
}
