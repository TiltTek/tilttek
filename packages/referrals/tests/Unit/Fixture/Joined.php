<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\MemberSince;

/**
 * Joining dates told by the test, by member reference.
 */
final class Joined implements MemberSince
{
    /**
     * @var array<string, DateTimeImmutable>
     */
    public array $dates = [];

    #[Override]
    public function joinedAt(ResourceReference $member): ?DateTimeImmutable
    {
        return $this->dates[$member->toString()] ?? null;
    }
}
