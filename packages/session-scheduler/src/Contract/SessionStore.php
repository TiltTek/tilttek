<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Contract;

use DateTimeInterface;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Exception\ScheduleConflict;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\Session;
use Tilttek\SessionScheduler\SessionQuery;

/**
 * Keeps the series and their sessions, each at a version.
 */
interface SessionStore
{
    /**
     * The series, or null when none has the identifier.
     */
    public function series(string $id): ?Series;

    /**
     * Keeps the series at the next version, and returns it. A series at version 0 is new; any other is kept only when
     * the store holds it at that version.
     *
     * @throws ScheduleConflict when the series is new and the store has one of its identifier, or the store holds it
     *                          at another version; nothing is kept
     */
    public function saveSeries(Series $series): Series;

    /**
     * The session, or null when none has the identifier.
     */
    public function session(string $id): ?Session;

    /**
     * Keeps the session at the next version, and returns it, as `saveSeries()` does a series.
     *
     * @throws ScheduleConflict when the session is new and the store has one of its identifier, or the store holds it
     *                          at another version; nothing is kept
     */
    public function save(Session $session): Session;

    /**
     * The sessions the query matches, the earliest first, then by identifier; at most the limit.
     *
     * @param int $limit 1 or more
     *
     * @return iterable<Session>
     *
     * @throws InvalidSchedule when the limit is below 1
     */
    public function sessions(SessionQuery $query, int $limit): iterable;

    /**
     * How many planned sessions starting after the moment the member said they are coming to, in every series.
     */
    public function countComing(string $member, DateTimeInterface $after): int;
}
