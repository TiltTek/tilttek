<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Fixture;

use Override;
use Phil\Resource\ResourceReference;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;

/**
 * The member of the test application: the reference the request carries in its header `X-Member`, or a visitor.
 */
final readonly class HeaderMember implements MemberResolver
{
    public function __construct(
        private RequestStack $requests,
    ) {
    }

    #[Override]
    public function current(): ?ResourceReference
    {
        $request = $this->requests->getCurrentRequest();
        $member = $request instanceof Request ? $request->headers->get('X-Member') : null;

        return null === $member || '' === $member ? null : ResourceReference::fromString($member);
    }
}
