<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Event;

use Tilttek\Bounties\BountyState;

/**
 * Dispatched once a bounty is settled — awarded, expired or cancelled —, after its points are paid and the rest given
 * back to its sponsor: `$refunded` says how many went back.
 */
final readonly class BountyClosed
{
    public function __construct(
        public string $bounty,
        public BountyState $state,
        public int $refunded,
    ) {
    }
}
