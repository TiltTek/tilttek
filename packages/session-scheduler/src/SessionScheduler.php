<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

use DateTimeImmutable;
use DateTimeInterface;
use Psr\Clock\ClockInterface;
use Tilttek\SessionScheduler\Contract\DateProposer;
use Tilttek\SessionScheduler\Contract\SessionObserver;
use Tilttek\SessionScheduler\Contract\SessionStore;
use Tilttek\SessionScheduler\Event\AbsenceAnnounced;
use Tilttek\SessionScheduler\Event\AttendanceConfirmed;
use Tilttek\SessionScheduler\Event\DatesProposed;
use Tilttek\SessionScheduler\Event\GameMasterAbsent;
use Tilttek\SessionScheduler\Event\QuorumLost;
use Tilttek\SessionScheduler\Event\QuorumRestored;
use Tilttek\SessionScheduler\Event\ScheduleEvent;
use Tilttek\SessionScheduler\Event\SeriesChanged;
use Tilttek\SessionScheduler\Event\SeriesCreated;
use Tilttek\SessionScheduler\Event\SessionCancelled;
use Tilttek\SessionScheduler\Event\SessionHeld;
use Tilttek\SessionScheduler\Event\SessionPlanned;
use Tilttek\SessionScheduler\Event\SessionRescheduled;
use Tilttek\SessionScheduler\Event\SubstituteAssigned;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Exception\ScheduleConflict;
use Tilttek\SessionScheduler\Exception\ScheduleNotFound;
use Tilttek\SessionScheduler\Exception\ScheduleRefused;
use Tilttek\SessionScheduler\Internal\Identifier;
use Tilttek\SessionScheduler\Internal\Moment;

/**
 * The service of the schedule: it creates and changes series, plans their sessions from the rule in batches, adds,
 * reschedules, postpones and cancels sessions, records who comes and who is absent, gives a session a substitute game
 * master, proposes dates, and lists the reminders due. It reads from the store, changes, keeps, then tells the
 * observer what happened, each fact once. It keeps no state between calls: everything is in the store, and the time
 * comes from the clock.
 *
 * The series' game master changes the series and its sessions; a member answers for themselves. A session is never
 * cancelled for want of players: the game master is told, and decides.
 */
final readonly class SessionScheduler
{
    /** The most sessions one call plans or postpones. */
    public const int MAX_PLAN = 100;

    /** The most sessions one call reads. */
    public const int MAX_READ = 1_000;

    /** The most days a postponement moves sessions by. */
    public const int MAX_POSTPONE = 366;

    /** The longest window of reminders one call lists, in days. */
    public const int MAX_REMINDER_WINDOW = 31;

    /**
     * @param ?int $maxComing the most upcoming sessions a member may say they are coming to, in every series, 1 to
     *                        100; null for no cap
     *
     * @throws InvalidSchedule when the cap is out of its bounds
     */
    public function __construct(
        private SessionStore $store,
        private ClockInterface $clock,
        private ?SessionObserver $observer = null,
        private ?DateProposer $proposer = null,
        private ?int $maxComing = null,
    ) {
        if (null !== $maxComing && ($maxComing < 1 || $maxComing > 100)) {
            throw InvalidSchedule::because('The cap of upcoming sessions per member is 1 to 100.');
        }
    }

    /**
     * @throws InvalidSchedule  when the identifier is not one
     * @throws ScheduleNotFound when no series has it
     */
    public function series(string $id): Series
    {
        return $this->store->series(Identifier::check($id, 'a series')) ?? throw ScheduleNotFound::series($id);
    }

    /**
     * @throws InvalidSchedule  when the identifier is not one
     * @throws ScheduleNotFound when no session has it
     */
    public function session(string $id): Session
    {
        return $this->store->session(Identifier::check($id, 'a session')) ?? throw ScheduleNotFound::session($id);
    }

    /**
     * The sessions the query matches, the earliest first.
     *
     * @param int $limit 1 to 1000
     *
     * @return iterable<Session>
     *
     * @throws InvalidSchedule when the limit is out of its bounds
     */
    public function sessions(SessionQuery $query, int $limit = self::MAX_READ): iterable
    {
        return $this->store->sessions($query, $this->bounded($limit, self::MAX_READ, 'A read of sessions'));
    }

    /**
     * Creates a new series, at version 0. No session is planned yet: `plan()` plans them.
     *
     * @throws ScheduleRefused  when the series is not at version 0, or a series has its identifier
     * @throws ScheduleConflict when a series of its identifier was kept meanwhile
     */
    public function createSeries(Series $series): Series
    {
        if (0 !== $series->version) {
            throw ScheduleRefused::because('A new series is at version 0.');
        }

        if ($this->store->series($series->id) instanceof Series) {
            throw ScheduleRefused::because(\sprintf('A series has the identifier %s.', $series->id));
        }

        $kept = $this->store->saveSeries($series);
        $this->tell(new SeriesCreated($kept->id));

        return $kept;
    }

    /**
     * Replaces a series with its change, made on the version read, by its game master; they may hand it to another.
     * The sessions already planned keep their dates.
     *
     * @throws ScheduleNotFound when no series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, or the change moves it to another campaign
     * @throws ScheduleConflict when the series changed since it was read
     */
    public function changeSeries(Series $series, string $by): Series
    {
        $before = $this->series($series->id);
        $this->refuseOthers($before, $by);

        if ($series->campaign !== $before->campaign) {
            throw ScheduleRefused::because('A series stays in its campaign.');
        }

        $kept = $this->store->saveSeries($series);
        $this->tell(new SeriesChanged($kept->id));

        return $kept;
    }

    /**
     * Plans the sessions of the rule's occurrences from now through a moment, at most so many: those already planned
     * — even rescheduled or cancelled since — are left as they are, so that planning again plans only what is new and
     * goes on where the previous call stopped. At most 1000 occurrences are read.
     *
     * @param int $limit 1 to 100 sessions planned
     *
     * @return list<Session> the sessions planned by this call, the earliest first
     *
     * @throws ScheduleNotFound when no series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master
     * @throws InvalidSchedule  when the limit is out of its bounds
     * @throws ScheduleConflict when a session of an occurrence was kept meanwhile
     */
    public function plan(string $series, DateTimeInterface $through, string $by, int $limit = self::MAX_PLAN): array
    {
        $kept = $this->series($series);
        $this->refuseOthers($kept, $by);
        $limit = $this->bounded($limit, self::MAX_PLAN, 'A plan');
        $planned = [];

        foreach ($kept->recurrence()->between($this->now(), $through, self::MAX_READ) as $occurrence) {
            $id = $kept->id.':'.Moment::stamp($occurrence);

            if ($this->store->session($id) instanceof Session) {
                continue;
            }

            $planned[] = $session = $this->store->save(new Session($id, $kept->id, $occurrence, $kept->minutes, $occurrence));
            $this->tell(new SessionPlanned($kept->id, $session->id, false));

            if (\count($planned) === $limit) {
                break;
            }
        }

        return $planned;
    }

    /**
     * Adds a session at a start of the game master's choosing. A start that is not one of the rule's occurrences is
     * marked off calendar, never refused.
     *
     * @throws ScheduleNotFound when no series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, the start is past, or a session of the
     *                          series stands for that start already
     * @throws ScheduleConflict when such a session was kept meanwhile
     */
    public function add(string $series, DateTimeInterface $startsAt, string $by): Session
    {
        $kept = $this->series($series);
        $this->refuseOthers($kept, $by);
        $this->refusePast($startsAt);
        $onRule = $kept->isOccurrence($startsAt);
        $id = $kept->id.($onRule ? ':' : ':x').Moment::stamp($startsAt);

        if ($this->store->session($id) instanceof Session) {
            throw ScheduleRefused::because('A session of the series stands for that start already.');
        }

        $session = $this->store->save(new Session($id, $kept->id, $startsAt, $kept->minutes, $onRule ? $startsAt : null, !$onRule));
        $this->tell(new SessionPlanned($kept->id, $session->id, $session->offCalendar));

        return $session;
    }

    /**
     * Moves a planned session to another start; the replies given for the old one are dropped, and the members answer
     * again. Moving it to the start it has changes nothing.
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, the session is not planned or has
     *                          started, or the new start is past
     * @throws ScheduleConflict when the session changed meanwhile
     */
    public function reschedule(string $session, DateTimeInterface $startsAt, string $by): Session
    {
        [$before, $series] = $this->upcoming($session);
        $this->refuseOthers($series, $by);
        $this->refusePast($startsAt);

        if (Moment::same($startsAt, $before->startsAt)) {
            return $before;
        }

        $kept = $this->store->save($before->movedTo($startsAt, !$series->isOccurrence($startsAt)));
        $this->tell(new SessionRescheduled($series->id, $kept->id, $kept->offCalendar));

        return $kept;
    }

    /**
     * Postpones a planned session and every later planned session of its series by so many days, on the wall clock of
     * each one's time zone: the spacing between them is kept. At most 100 sessions move; the replies are dropped.
     *
     * @param int $days 1 to 366
     *
     * @return list<Session> the sessions moved, the earliest first
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, or the session is not planned or has
     *                          started
     * @throws InvalidSchedule  when the days are out of their bounds
     * @throws ScheduleConflict when a session changed meanwhile
     */
    public function postpone(string $session, int $days, string $by): array
    {
        [$first, $series] = $this->upcoming($session);
        $this->refuseOthers($series, $by);
        $days = $this->bounded($days, self::MAX_POSTPONE, 'A postponement in days');
        $moved = [];

        foreach ($this->store->sessions(SessionQuery::ofSeries($series->id, $first->startsAt, null, SessionStatus::Planned), self::MAX_PLAN) as $later) {
            $startsAt = $later->startsAt->modify(\sprintf('+%d days', $days));
            $moved[] = $kept = $this->store->save($later->movedTo($startsAt, !$series->isOccurrence($startsAt)));
            $this->tell(new SessionRescheduled($series->id, $kept->id, $kept->offCalendar));
        }

        return $moved;
    }

    /**
     * Cancels a planned session that has not started.
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, or the session is not planned or has
     *                          started
     * @throws ScheduleConflict when the session changed meanwhile
     */
    public function cancel(string $session, string $by): Session
    {
        [$before, $series] = $this->upcoming($session);
        $this->refuseOthers($series, $by);
        $kept = $this->store->save($before->withStatus(SessionStatus::Cancelled));
        $this->tell(new SessionCancelled($series->id, $kept->id));

        return $kept;
    }

    /**
     * Marks a planned session held, once it has started: by the series' game master or the substitute who ran it.
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member runs neither the series nor the session, the session is not planned, or
     *                          it has not started
     * @throws ScheduleConflict when the session changed meanwhile
     */
    public function hold(string $session, string $by): Session
    {
        $before = $this->session($session);
        $series = $this->series($before->series);

        if ($by !== $series->gameMaster && $by !== $before->gameMaster) {
            throw ScheduleRefused::because('Only a game master of the session marks it held.');
        }

        if (SessionStatus::Planned !== $before->status) {
            throw ScheduleRefused::because('Only a planned session is held.');
        }

        if ($before->startsAt > $this->now()) {
            throw ScheduleRefused::because('A session is held once it has started.');
        }

        $kept = $this->store->save($before->withStatus(SessionStatus::Held));
        $this->tell(new SessionHeld($series->id, $kept->id));

        return $kept;
    }

    /**
     * Gives a planned session a substitute game master, or gives it back to the series' one with null or with them.
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, the session is not planned or has
     *                          started, or the substitute is one of the players
     * @throws InvalidSchedule  when the substitute is not an identifier
     * @throws ScheduleConflict when the session changed meanwhile
     */
    public function substitute(string $session, ?string $gameMaster, string $by): Session
    {
        [$before, $series] = $this->upcoming($session);
        $this->refuseOthers($series, $by);

        if (null !== $gameMaster && \in_array(Identifier::check($gameMaster, 'a member'), $series->players, true)) {
            throw ScheduleRefused::because('A substitute game master is not one of the players.');
        }

        $substitute = $gameMaster === $series->gameMaster ? null : $gameMaster;

        if ($substitute === $before->gameMaster) {
            return $before;
        }

        $kept = $this->store->save($before->withGameMaster($substitute));
        $this->tell(new SubstituteAssigned($series->id, $kept->id, $substitute));

        return $kept;
    }

    /**
     * The member says they are coming to a planned session that has not started. Saying it again changes nothing.
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member neither plays nor runs the session, the session is not planned or has
     *                          started, or the member reached the cap of upcoming sessions
     * @throws ScheduleConflict when the session changed meanwhile
     */
    public function confirm(string $session, string $member): Session
    {
        [$before, $series] = $this->upcoming($session);
        $this->refuseStrangers($series, $before, $member);

        if (Reply::Coming === $before->reply($member)) {
            return $before;
        }

        if (null !== $this->maxComing && $this->store->countComing($member, $this->now()) >= $this->maxComing) {
            throw ScheduleRefused::because(\sprintf('A member comes to at most %d upcoming sessions.', $this->maxComing));
        }

        $kept = $this->store->save($before->withReply($member, Reply::Coming));
        $this->tell(new AttendanceConfirmed($series->id, $kept->id, $member));

        if ($this->present($series, $before) < $series->quorum && $this->present($series, $kept) >= $series->quorum) {
            $this->tell(new QuorumRestored($series->id, $kept->id));
        }

        return $kept;
    }

    /**
     * The member says they are absent from a planned session that has not started. Later than the series' notice, the
     * absence is recorded all the same, marked late, for the game master to decide. Saying it again changes nothing.
     * The game master who runs the session says it too: the session then waits for a substitute, or to be cancelled.
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member neither plays nor runs the session, or the session is not planned or has
     *                          started
     * @throws ScheduleConflict when the session changed meanwhile
     */
    public function absent(string $session, string $member): Session
    {
        [$before, $series] = $this->upcoming($session);
        $this->refuseStrangers($series, $before, $member);

        if (true === $before->reply($member)?->isAbsent()) {
            return $before;
        }

        $late = $this->now() > $before->startsAt->modify(\sprintf('-%d hours', $series->notice));
        $kept = $this->store->save($before->withReply($member, $late ? Reply::AbsentLate : Reply::Absent));

        if ($member === ($before->gameMaster ?? $series->gameMaster)) {
            $this->tell(new GameMasterAbsent($series->id, $kept->id, $member, $late));

            return $kept;
        }

        $this->tell(new AbsenceAnnounced($series->id, $kept->id, $member, $late));

        if ($this->present($series, $before) >= $series->quorum && $this->present($series, $kept) < $series->quorum) {
            $this->tell(new QuorumLost($series->id, $kept->id));
        }

        return $kept;
    }

    /**
     * Puts dates to the members of a series for a new session: its first, or one to add. The answer comes back when the
     * application calls `add()` with the date chosen.
     *
     * @param list<DateTimeInterface> $candidates 1 to 20 distinct starts, none past
     *
     * @throws ScheduleNotFound when no series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, or a date is past
     * @throws InvalidSchedule  when the dates are out of their bounds
     */
    public function propose(string $series, array $candidates, string $by): DateProposal
    {
        $kept = $this->series($series);
        $this->refuseOthers($kept, $by);

        foreach ($candidates as $candidate) {
            $this->refusePast($candidate);
        }

        return $this->put(new DateProposal($kept->id, null, $candidates, [$kept->gameMaster, ...$kept->players], $kept->minutes));
    }

    /**
     * Puts dates to the members of a series to replace a session, planned or cancelled: the days from tomorrow through
     * a moment, at the series' time of day on its wall clock, on which no other planned session of the series starts,
     * at most so many. The answer comes back when the application calls `reschedule()` with the date chosen.
     *
     * @param int $count 1 to 20 dates
     *
     * @throws ScheduleNotFound when no session or series has the identifier
     * @throws ScheduleRefused  when the member is not the series' game master, the session was held, or no day is free
     * @throws InvalidSchedule  when the count is out of its bounds
     */
    public function proposeReplacement(string $session, DateTimeInterface $through, string $by, int $count = 7): DateProposal
    {
        $replaced = $this->session($session);
        $series = $this->series($replaced->series);
        $this->refuseOthers($series, $by);
        $count = $this->bounded($count, DateProposal::MAX_CANDIDATES, 'A proposal');

        if (SessionStatus::Held === $replaced->status) {
            throw ScheduleRefused::because('A session held is not replaced.');
        }

        $zone = $series->start->getTimezone();
        $now = $this->now()->setTimezone($zone);
        $taken = [];

        foreach ($this->store->sessions(SessionQuery::ofSeries($series->id, $now, $through, SessionStatus::Planned), self::MAX_READ) as $planned) {
            if ($planned->id !== $replaced->id) {
                $taken[$planned->startsAt->setTimezone($zone)->format('Y-m-d')] = true;
            }
        }

        [$hours, $minutes] = array_map(intval(...), explode(':', $series->start->format('G:i')));
        $candidates = [];
        $day = $now->modify('+1 day')->setTime($hours, $minutes);

        for ($walked = 0; $walked < self::MAX_POSTPONE && \count($candidates) < $count && $day <= $through; ++$walked, $day = $day->modify('+1 day')) {
            if (!isset($taken[$day->format('Y-m-d')])) {
                $candidates[] = $day;
            }
        }

        if ([] === $candidates) {
            throw ScheduleRefused::because('No day is free for the series before the end of the window.');
        }

        return $this->put(new DateProposal($series->id, $replaced->id, $candidates, [$replaced->gameMaster ?? $series->gameMaster, ...$series->players], $replaced->minutes));
    }

    /**
     * The reminders due after one moment through another, the earliest due first: the application's scheduled task
     * passes the end of its previous call as the start of the next, so that each reminder is due once. A reminder is due
     * the series' reminder hours before a planned session; a series without one has none.
     *
     * @return list<Reminder>
     *
     * @throws InvalidSchedule when the window ends before it starts, or is longer than 31 days
     */
    public function reminders(DateTimeInterface $after, DateTimeInterface $through): array
    {
        if ($through < $after || DateTimeImmutable::createFromInterface($after)->modify(\sprintf('+%d days', self::MAX_REMINDER_WINDOW)) < $through) {
            throw InvalidSchedule::because(\sprintf('A window of reminders ends after it starts, at most %d days later.', self::MAX_REMINDER_WINDOW));
        }

        $series = [];
        $reminders = [];
        $query = SessionQuery::starting($after, DateTimeImmutable::createFromInterface($through)->modify(\sprintf('+%d hours', Series::MAX_REMINDER)), SessionStatus::Planned);

        foreach ($this->store->sessions($query, self::MAX_READ) as $session) {
            $of = $series[$session->series] ??= $this->store->series($session->series);

            if (null === $of?->reminder) {
                continue;
            }

            $dueAt = $session->startsAt->modify(\sprintf('-%d hours', $of->reminder));

            if ($dueAt > $after && $dueAt <= $through) {
                $reminders[] = new Reminder($of->id, $session->id, $session->startsAt, $dueAt, $this->reminded($of, $session));
            }
        }

        usort($reminders, static fn (Reminder $one, Reminder $other): int => [$one->dueAt, $one->session] <=> [$other->dueAt, $other->session]);

        return $reminders;
    }

    /**
     * The game master who runs the session and the players, without those who said they are absent.
     *
     * @return list<string>
     */
    private function reminded(Series $series, Session $session): array
    {
        return array_values(array_filter(
            [$session->gameMaster ?? $series->gameMaster, ...$series->players],
            static fn (string $member): bool => true !== $session->reply($member)?->isAbsent(),
        ));
    }

    /**
     * The players of the series not absent from the session.
     */
    private function present(Series $series, Session $session): int
    {
        return \count(array_filter($series->players, static fn (string $player): bool => true !== $session->reply($player)?->isAbsent()));
    }

    /**
     * A planned session that has not started, and its series.
     *
     * @return array{Session, Series}
     *
     * @throws ScheduleNotFound
     * @throws ScheduleRefused
     */
    private function upcoming(string $id): array
    {
        $session = $this->session($id);

        if (SessionStatus::Planned !== $session->status) {
            throw ScheduleRefused::because('Only a planned session changes.');
        }

        if ($session->startsAt <= $this->now()) {
            throw ScheduleRefused::because('A session that has started no longer changes.');
        }

        return [$session, $this->series($session->series)];
    }

    private function put(DateProposal $proposal): DateProposal
    {
        $this->proposer?->propose($proposal);
        $this->tell(new DatesProposed($proposal->series, $proposal->session));

        return $proposal;
    }

    private function refuseOthers(Series $series, string $by): void
    {
        if ($by !== $series->gameMaster) {
            throw ScheduleRefused::because('Only the game master of the series changes it and its sessions.');
        }
    }

    private function refuseStrangers(Series $series, Session $session, string $member): void
    {
        if ($member !== ($session->gameMaster ?? $series->gameMaster) && !\in_array($member, $series->players, true)) {
            throw ScheduleRefused::because('Only a player of the series or the game master of the session answers for it.');
        }
    }

    private function refusePast(DateTimeInterface $moment): void
    {
        if ($moment <= $this->now()) {
            throw ScheduleRefused::because('A session starts after now.');
        }
    }

    private function bounded(int $value, int $max, string $what): int
    {
        if ($value < 1 || $value > $max) {
            throw InvalidSchedule::because(\sprintf('%s is 1 to %d.', $what, $max));
        }

        return $value;
    }

    private function now(): DateTimeImmutable
    {
        return $this->clock->now();
    }

    private function tell(ScheduleEvent $event): void
    {
        $this->observer?->notify($event);
    }
}
