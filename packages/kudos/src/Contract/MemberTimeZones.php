<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Contract;

use DateTimeZone;
use Phil\Resource\ResourceReference;

/**
 * The time zone a member's day is counted in: theirs when they chose one, the site's otherwise.
 */
interface MemberTimeZones
{
    public function of(ResourceReference $member): DateTimeZone;
}
