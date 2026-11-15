<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Event;

/**
 * Dispatched once the sponsor awards points to a claim; they are paid when the bounty is settled.
 */
final readonly class AwardGiven
{
    public function __construct(
        public string $bounty,
        public string $award,
        public string $claim,
        public int $amount,
    ) {
    }
}
