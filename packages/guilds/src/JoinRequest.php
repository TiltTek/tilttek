<?php

declare(strict_types=1);

namespace Tilttek\Guilds;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A member's request to enter a guild on request, waiting for its founder or an administrator.
 */
final readonly class JoinRequest
{
    public function __construct(
        public ResourceReference $guild,
        public ResourceReference $member,
        public DateTimeImmutable $requestedAt,
    ) {
    }
}
