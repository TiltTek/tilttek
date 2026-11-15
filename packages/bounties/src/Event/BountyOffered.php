<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Event;

use Phil\Resource\ResourceReference;

/**
 * Dispatched once a bounty is offered and its points held.
 */
final readonly class BountyOffered
{
    public function __construct(
        public string $bounty,
        public ResourceReference $sponsor,
        public ResourceReference $subject,
    ) {
    }
}
