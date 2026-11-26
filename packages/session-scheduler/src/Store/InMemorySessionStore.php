<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler\Store;

use DateTimeInterface;
use Override;
use Tilttek\SessionScheduler\Contract\SessionStore;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Exception\ScheduleConflict;
use Tilttek\SessionScheduler\Reply;
use Tilttek\SessionScheduler\Series;
use Tilttek\SessionScheduler\Session;
use Tilttek\SessionScheduler\SessionQuery;
use Tilttek\SessionScheduler\SessionStatus;

/**
 * The series and sessions in memory, for tests: they last as long as the object.
 */
final class InMemorySessionStore implements SessionStore
{
    /** @var array<string, Series> by identifier */
    private array $series = [];

    /** @var array<string, Session> by identifier */
    private array $sessions = [];

    #[Override]
    public function series(string $id): ?Series
    {
        return $this->series[$id] ?? null;
    }

    #[Override]
    public function saveSeries(Series $series): Series
    {
        if ($series->version !== ($this->series[$series->id]->version ?? 0)) {
            throw ScheduleConflict::changed('The series '.$series->id);
        }

        return $this->series[$series->id] = $series->withVersion($series->version + 1);
    }

    #[Override]
    public function session(string $id): ?Session
    {
        return $this->sessions[$id] ?? null;
    }

    #[Override]
    public function save(Session $session): Session
    {
        if ($session->version !== ($this->sessions[$session->id]->version ?? 0)) {
            throw ScheduleConflict::changed('The session '.$session->id);
        }

        return $this->sessions[$session->id] = $session->withVersion($session->version + 1);
    }

    #[Override]
    public function sessions(SessionQuery $query, int $limit): iterable
    {
        if ($limit < 1) {
            throw InvalidSchedule::because('A store reads at least one session at once.');
        }

        $sessions = array_filter($this->sessions, $query->matches(...));
        usort($sessions, static fn (Session $one, Session $other): int => [$one->startsAt, $one->id] <=> [$other->startsAt, $other->id]);

        return \array_slice($sessions, 0, $limit);
    }

    #[Override]
    public function countComing(string $member, DateTimeInterface $after): int
    {
        return \count(array_filter(
            $this->sessions,
            static fn (Session $session): bool => SessionStatus::Planned === $session->status && $session->startsAt > $after && Reply::Coming === $session->reply($member),
        ));
    }
}
