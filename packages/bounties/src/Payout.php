<?php

declare(strict_types=1);

namespace Tilttek\Bounties;

use Phil\Resource\ResourceReference;
use Tilttek\Bounties\Exception\InvalidBounty;

/**
 * Points of a hold paid to a member when the hold is confirmed.
 */
final readonly class Payout
{
    public function __construct(
        public ResourceReference $member,
        public int $amount,
    ) {
        if ($amount < 1) {
            throw InvalidBounty::because(\sprintf('A payout is at least one point; %d is not.', $amount));
        }
    }
}
