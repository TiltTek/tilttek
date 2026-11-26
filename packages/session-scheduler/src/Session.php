<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Internal\Identifier;

/**
 * One session of a series: when it starts, how long it lasts, where it stands, and the members' replies.
 *
 * A session planned from the rule keeps the occurrence it stands for, even once rescheduled, so that planning the
 * series again never plans that occurrence twice. A session off the rule's occurrences is marked off calendar, never
 * refused. A substitute game master runs it in place of the series' one when given.
 */
final readonly class Session
{
    /** The most replies a session holds: every player and the game master. */
    public const int MAX_REPLIES = Series::MAX_PLAYERS + 1;

    public DateTimeImmutable $startsAt;

    public ?DateTimeImmutable $occurrence;

    /**
     * @param array<string, Reply> $replies by member, at most 51
     *
     * @throws InvalidSchedule when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $id,
        public string $series,
        DateTimeInterface $startsAt,
        public int $minutes,
        ?DateTimeInterface $occurrence = null,
        public bool $offCalendar = false,
        public SessionStatus $status = SessionStatus::Planned,
        public ?string $gameMaster = null,
        public array $replies = [],
        public int $version = 0,
    ) {
        Identifier::check($id, 'a session');
        Identifier::check($series, 'a series');

        if (null !== $gameMaster) {
            Identifier::check($gameMaster, 'a member');
        }

        foreach (array_keys($replies) as $member) {
            Identifier::check($member, 'a member');
        }

        $reason = match (true) {
            $minutes < Series::MIN_MINUTES || $minutes > Series::MAX_MINUTES => \sprintf('A session lasts %d to %d minutes.', Series::MIN_MINUTES, Series::MAX_MINUTES),
            \count($replies) > self::MAX_REPLIES => \sprintf('A session holds at most %d replies.', self::MAX_REPLIES),
            $version < 0 => 'The version of a session is 0 or more.',
            default => null,
        };

        if (null !== $reason) {
            throw InvalidSchedule::because($reason);
        }

        $this->startsAt = DateTimeImmutable::createFromInterface($startsAt);
        $this->occurrence = $occurrence instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($occurrence) : null;
    }

    public function endsAt(): DateTimeImmutable
    {
        return $this->startsAt->modify(\sprintf('+%d minutes', $this->minutes));
    }

    /**
     * The member's reply, or null when they have not answered.
     */
    public function reply(string $member): ?Reply
    {
        return $this->replies[$member] ?? null;
    }

    /**
     * Whether the session moved from the occurrence it stands for.
     */
    public function isRescheduled(): bool
    {
        return $this->occurrence instanceof DateTimeImmutable && $this->occurrence->format('U.u') !== $this->startsAt->format('U.u');
    }

    public function withVersion(int $version): self
    {
        return new self($this->id, $this->series, $this->startsAt, $this->minutes, $this->occurrence, $this->offCalendar, $this->status, $this->gameMaster, $this->replies, $version);
    }

    /**
     * The session moved to another start: the replies given for the old one are dropped.
     */
    public function movedTo(DateTimeInterface $startsAt, bool $offCalendar): self
    {
        return new self($this->id, $this->series, $startsAt, $this->minutes, $this->occurrence, $offCalendar, $this->status, $this->gameMaster, [], $this->version);
    }

    public function withStatus(SessionStatus $status): self
    {
        return new self($this->id, $this->series, $this->startsAt, $this->minutes, $this->occurrence, $this->offCalendar, $status, $this->gameMaster, $this->replies, $this->version);
    }

    public function withReply(string $member, Reply $reply): self
    {
        $replies = $this->replies;
        $replies[$member] = $reply;

        return new self($this->id, $this->series, $this->startsAt, $this->minutes, $this->occurrence, $this->offCalendar, $this->status, $this->gameMaster, $replies, $this->version);
    }

    public function withGameMaster(?string $gameMaster): self
    {
        return new self($this->id, $this->series, $this->startsAt, $this->minutes, $this->occurrence, $this->offCalendar, $this->status, $gameMaster, $this->replies, $this->version);
    }
}
