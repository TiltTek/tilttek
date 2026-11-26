<?php

declare(strict_types=1);

namespace Tilttek\SessionScheduler;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;
use Tilttek\SessionScheduler\Exception\InvalidSchedule;
use Tilttek\SessionScheduler\Internal\Identifier;

/**
 * The sessions of a campaign as they repeat: a first session, in its time zone, an RFC 5545 rule, how long a session
 * lasts, who runs it and who plays. Sessions keep the first one's time of day on the wall clock of its time zone,
 * across changes of time.
 *
 * The quorum is the fewest players not absent for a session to be worth holding: below it the game master is told,
 * and decides. An absence announced later than the notice before a session is still recorded, marked late. The table
 * sets one reminder before each session, or none.
 */
final readonly class Series
{
    /** The longest identifier of a series: its sessions' identifiers add their instant to it. */
    public const int MAX_ID_LENGTH = 96;

    public const int MAX_PLAYERS = 50;

    public const int MIN_MINUTES = 15;

    public const int MAX_MINUTES = 1440;

    /** The longest notice an absence is asked to give, in hours: thirty days. */
    public const int MAX_NOTICE = 720;

    /** The earliest reminder before a session, in hours: a week. */
    public const int MAX_REMINDER = 168;

    public const int DEFAULT_NOTICE = 24;

    public DateTimeImmutable $start;

    /** @var list<string> */
    public array $players;

    /**
     * @param string       $id         the series' identifier, at most 96 characters
     * @param string       $campaign   the campaign's identifier
     * @param string       $gameMaster the member who runs the sessions and changes the series
     * @param list<string> $players    the members who play, at most 50, the game master not among them
     * @param int          $minutes    how long a session lasts, 15 to 1440
     * @param int          $quorum     the fewest players not absent for a session to be worth holding, 1 to their number
     * @param int          $notice     the hours an absence is announced before a session, 0 to 720; later, it is late
     * @param ?int         $reminder   the hours before a session its reminder is due, 1 to 168, or null for none
     * @param int          $version    0 for a new series; the store's version otherwise
     *
     * @throws InvalidSchedule when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $id,
        public string $campaign,
        public string $gameMaster,
        DateTimeInterface $start,
        public RecurrenceRule $rule,
        array $players,
        public int $minutes = 180,
        public int $quorum = 1,
        public int $notice = self::DEFAULT_NOTICE,
        public ?int $reminder = null,
        public int $version = 0,
    ) {
        Identifier::check($id, 'a series');
        Identifier::check($campaign, 'a campaign');
        Identifier::check($gameMaster, 'a member');
        $this->start = DateTimeImmutable::createFromInterface($start);
        $this->players = Identifier::members($players, self::MAX_PLAYERS, 'A series');

        $reason = match (true) {
            \strlen($id) > self::MAX_ID_LENGTH => \sprintf('The identifier of a series is at most %d characters.', self::MAX_ID_LENGTH),
            \in_array($gameMaster, $this->players, true) => 'The game master of a series is not one of its players.',
            $minutes < self::MIN_MINUTES || $minutes > self::MAX_MINUTES => \sprintf('A session lasts %d to %d minutes.', self::MIN_MINUTES, self::MAX_MINUTES),
            $quorum < 1 || $quorum > max(1, \count($this->players)) => 'The quorum of a series is 1 to its number of players.',
            $notice < 0 || $notice > self::MAX_NOTICE => \sprintf('The notice of an absence is 0 to %d hours.', self::MAX_NOTICE),
            null !== $reminder && ($reminder < 1 || $reminder > self::MAX_REMINDER) => \sprintf('A reminder is due 1 to %d hours before a session.', self::MAX_REMINDER),
            $version < 0 => 'The version of a series is 0 or more.',
            default => null,
        };

        if (null !== $reason) {
            throw InvalidSchedule::because($reason);
        }
    }

    /**
     * The occurrences of the rule, from its first session.
     */
    public function recurrence(): Recurrence
    {
        return Recurrence::of($this->start, $this->rule);
    }

    /**
     * Whether the moment is one of the rule's occurrences.
     */
    public function isOccurrence(DateTimeInterface $moment): bool
    {
        return [] !== $this->recurrence()->between($moment, $moment, 1);
    }

    public function withVersion(int $version): self
    {
        return new self($this->id, $this->campaign, $this->gameMaster, $this->start, $this->rule, $this->players, $this->minutes, $this->quorum, $this->notice, $this->reminder, $version);
    }
}
