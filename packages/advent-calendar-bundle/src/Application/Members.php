<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application;

use LogicException;
use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;

/**
 * The current member as the application's resolver gives them, checked: a reference whose identifier looks like an
 * e-mail address is refused, so that an address never becomes the key of a member's openings.
 */
final readonly class Members
{
    public function __construct(
        private MemberResolver $resolver,
    ) {
    }

    /**
     * @throws LogicException when the resolver gives an e-mail address
     */
    public function current(): ?ResourceReference
    {
        $member = $this->resolver->current();

        if ($member instanceof ResourceReference && str_contains($member->id, '@')) {
            throw new LogicException(\sprintf('The member resolver gave "%s:…", an identifier with "@": a member is referenced by a stable identifier, never by an e-mail address.', $member->key));
        }

        return $member;
    }
}
