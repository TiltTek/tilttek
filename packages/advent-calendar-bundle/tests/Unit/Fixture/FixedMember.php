<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;

/**
 * A member given once, or a visitor.
 */
final readonly class FixedMember implements MemberResolver
{
    public function __construct(
        private ?string $member = null,
    ) {
    }

    #[Override]
    public function current(): ?ResourceReference
    {
        return null === $this->member ? null : ResourceReference::fromString($this->member);
    }
}
