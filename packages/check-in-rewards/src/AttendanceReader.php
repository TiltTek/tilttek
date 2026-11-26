<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards;

use Phil\Resource\ResourceReference;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Journal;
use Tilttek\CheckInRewards\Exception\AccessDenied;

/**
 * Reads a member's attendance for that member, from the totals of the journal. There is no other reading: no ranking
 * of attendance, no list of who came, nothing that shows the habits of someone else.
 */
final readonly class AttendanceReader
{
    public function __construct(
        private Journal $journal,
    ) {
    }

    /**
     * The member's attendance — of the event when one is given —, read by the member themselves.
     *
     * @throws AccessDenied when the viewer is not the member
     */
    public function summary(ResourceReference $viewer, ResourceReference $member, ?ResourceReference $event = null): AttendanceSummary
    {
        if (!$viewer->equals($member)) {
            throw AccessDenied::toAttendanceOf($viewer->toString(), $member->toString());
        }

        $filter = Filter::all()->member($member);
        $ofEvent = $event instanceof ResourceReference ? $filter->object($event) : $filter;

        return new AttendanceSummary(
            $this->journal->totals($ofEvent->verbs(AttendanceVerbs::SESSION))->count,
            $this->journal->totals($ofEvent->verbs(AttendanceVerbs::DAY))->count,
            $this->journal->totals($ofEvent->verbs(AttendanceVerbs::PLACE))->count,
            $this->journal->totals($filter->verbs(AttendanceVerbs::WEEK))->count,
        );
    }
}
