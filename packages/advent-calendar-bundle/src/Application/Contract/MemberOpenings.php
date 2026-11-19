<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\Opening;

/**
 * Every opening of a member, in every calendar — those of a calendar since removed too —, for the export of their
 * data. The stores of the bundle implement it beside `OpeningStore`.
 */
interface MemberOpenings
{
    /**
     * @return list<Opening> by calendar, then by day
     */
    public function ofMember(ResourceReference $member): array;
}
