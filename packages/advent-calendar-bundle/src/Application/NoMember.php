<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application;

use Override;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberResolver;

/**
 * The resolver of an application that named none: every viewer is a visitor, who sees the calendar and opens nothing.
 */
final readonly class NoMember implements MemberResolver
{
    #[Override]
    public function current(): null
    {
        return null;
    }
}
