<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\MemberSince;

/**
 * Joining dates the test gives, by member reference.
 */
final readonly class JoiningDates implements MemberSince
{
    /**
     * @param array<string, string> $dates
     */
    public function __construct(
        private array $dates,
    ) {
    }

    #[Override]
    public function joinedAt(ResourceReference $member): ?DateTimeImmutable
    {
        $date = $this->dates[$member->toString()] ?? null;

        return null === $date ? null : new DateTimeImmutable($date);
    }
}
