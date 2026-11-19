<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application\Contract;

use Phil\Resource\ResourceReference;

/**
 * Who views the calendar: the application's reference of the current member, `member:44`, or null for a visitor. The
 * reference is a stable identifier of the member — never their e-mail address, which they may change and which says
 * who they are —; the bundle refuses one that looks like an address.
 */
interface MemberResolver
{
    public function current(): ?ResourceReference;
}
