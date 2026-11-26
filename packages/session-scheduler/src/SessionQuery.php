<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Internal\Identifier;

/**
 * Which sessions to read: those of a series or of every series, starting from a moment through another, in a status.
 * Both ends are included; a missing end leaves that side open.
 */
final readonly class SessionQuery
{
    public ?DateTimeImmutable $from;

    public ?DateTimeImmutable $through;

    /**
     * @throws InvalidSchedule when the series is not an identifier, or the window ends before it starts
     */
    public function __construct(
        public ?string $series = null,
        ?DateTimeInterface $from = null,
        ?DateTimeInterface $through = null,
        public ?SessionStatus $status = null,
    ) {
        if (null !== $series) {
            Identifier::check($series, 'a series');
        }

        if ($from instanceof DateTimeInterface && $through instanceof DateTimeInterface && $through < $from) {
            throw InvalidSchedule::because('A window of sessions ends after it starts.');
        }

        $this->from = $from instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($from) : null;
        $this->through = $through instanceof DateTimeInterface ? DateTimeImmutable::createFromInterface($through) : null;
    }

    /**
     * @throws InvalidSchedule
     */
    public static function ofSeries(string $series, ?DateTimeInterface $from = null, ?DateTimeInterface $through = null, ?SessionStatus $status = null): self
    {
        return new self($series, $from, $through, $status);
    }

    /**
     * The sessions of every series starting in a window.
     *
     * @throws InvalidSchedule
     */
    public static function starting(DateTimeInterface $from, DateTimeInterface $through, ?SessionStatus $status = null): self
    {
        return new self(null, $from, $through, $status);
    }

    /**
     * Whether the query reads the session: what a store's own query means.
     */
    public function matches(Session $session): bool
    {
        return (null === $this->series || $this->series === $session->series)
            && (!$this->status instanceof SessionStatus || $this->status === $session->status)
            && (!$this->from instanceof DateTimeImmutable || $session->startsAt >= $this->from)
            && (!$this->through instanceof DateTimeImmutable || $session->startsAt <= $this->through);
    }
}
