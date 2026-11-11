<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * Tells when a member joined, for the delay a new account waits before it exchanges with others. The application
 * answers from its accounts; the library never reads them.
 */
interface MemberSince
{
    /**
     * When the member joined; null when the application does not know the member.
     */
    public function joinedAt(ResourceReference $member): ?DateTimeImmutable;
}
