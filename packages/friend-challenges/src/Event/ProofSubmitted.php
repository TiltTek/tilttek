<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once a participant submits a proof: the judges of the challenge have one to review.
 */
final readonly class ProofSubmitted
{
    public function __construct(
        public string $challenge,
        public string $proof,
        public ResourceReference $participant,
    ) {
    }
}
