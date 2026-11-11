<?php

declare(strict_types=1);

namespace Tilttek\Exchange;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;

/**
 * The answer when the application tells no joining date: no member is known, so guards with a delay for new
 * accounts refuse every member. It is the default of guards without a delay, which never ask.
 */
final readonly class NoMemberSince implements MemberSince
{
    #[Override]
    public function joinedAt(ResourceReference $member): ?DateTimeImmutable
    {
        return null;
    }
}
