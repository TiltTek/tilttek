<?php

declare(strict_types=1);

namespace Tilttek\CheckInRewards;

/**
 * The verbs the library records in the journal of `tilttek/activity`. An application that declares its verbs to the
 * journal declares `attendance.*`, {@see self::PATTERN}.
 *
 * Each admission is recorded once as `attendance.recorded`; the first admission of a member to a session, on a day, at
 * a place and in a week is recorded once more under its own verb, so that a count of activities is a count of
 * distinct sessions, days, places and weeks — what the criteria of `tilttek/badges` and `tilttek/quests` read.
 */
final class AttendanceVerbs
{
    /**
     * The pattern of every verb of the library.
     */
    public const string PATTERN = 'attendance.*';

    /**
     * One admission at the door, recorded under the scan's identifier.
     */
    public const string RECORDED = 'attendance.recorded';

    /**
     * The first admission of a member to a session, a table or a stand of an event.
     */
    public const string SESSION = 'attendance.session';

    /**
     * The first admission of a member on a day of an event, in the event's timezone.
     */
    public const string DAY = 'attendance.day';

    /**
     * The first admission of a member at a place of an event: a table, a room, a stand.
     */
    public const string PLACE = 'attendance.place';

    /**
     * The first admission of a member in an ISO week, in the event's timezone.
     */
    public const string WEEK = 'attendance.week';

    private function __construct()
    {
    }
}
