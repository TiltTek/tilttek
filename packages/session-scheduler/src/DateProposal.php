<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Internal\Identifier;

/**
 * Dates put to the members of a series: for its first session, for an added one, or for a session to replace. The
 * candidates are distinct starts, the earliest first; every member answers in their own time zone, the dates name
 * instants.
 */
final readonly class DateProposal
{
    public const int MAX_CANDIDATES = 20;

    /** @var list<DateTimeImmutable> */
    public array $candidates;

    /** @var list<string> */
    public array $members;

    /**
     * @param ?string                 $session    the session the date replaces, or null for a new one
     * @param list<DateTimeInterface> $candidates 1 to 20 distinct starts
     * @param list<string>            $members    who is asked: the game master, then the players
     * @param int                     $minutes    how long the session lasts
     *
     * @throws InvalidSchedule when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $series,
        public ?string $session,
        array $candidates,
        array $members,
        public int $minutes,
    ) {
        Identifier::check($series, 'a series');

        if (null !== $session) {
            Identifier::check($session, 'a session');
        }

        if ([] === $candidates || \count($candidates) > self::MAX_CANDIDATES) {
            throw InvalidSchedule::because(\sprintf('A proposal holds 1 to %d dates.', self::MAX_CANDIDATES));
        }

        $kept = [];

        foreach ($candidates as $candidate) {
            $instant = $candidate->format('U.u');

            if (isset($kept[$instant])) {
                throw InvalidSchedule::because('A proposal holds each date once.');
            }

            $kept[$instant] = DateTimeImmutable::createFromInterface($candidate);
        }

        usort($kept, static fn (DateTimeImmutable $one, DateTimeImmutable $other): int => $one <=> $other);
        $this->candidates = $kept;
        $this->members = Identifier::members($members, Session::MAX_REPLIES, 'A proposal');

        if ($minutes < Series::MIN_MINUTES || $minutes > Series::MAX_MINUTES) {
            throw InvalidSchedule::because(\sprintf('A session lasts %d to %d minutes.', Series::MIN_MINUTES, Series::MAX_MINUTES));
        }
    }
}
