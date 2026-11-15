<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once a member claims a bounty: its sponsor has a claim to look at.
 */
final readonly class BountyClaimed
{
    public function __construct(
        public string $bounty,
        public string $claim,
        public ResourceReference $claimant,
    ) {
    }
}
