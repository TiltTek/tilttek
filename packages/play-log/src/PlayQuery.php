<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

use DateTimeImmutable;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Internal\Identifier;

/**
 * Which plays to read: those of a member, those waiting for a member's confirmation, those a member recorded, or those
 * of a group — always one of them, so that a store reads them by an index — narrowed to a game and to a period. A
 * period leaves out the plays whose date is unknown.
 */
final readonly class PlayQuery
{
    private function __construct(
        public PlayQueryKind $kind,
        public string $subject,
        public ?string $game = null,
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $until = null,
    ) {
    }

    /**
     * The plays where the member holds a confirmed seat.
     *
     * @throws InvalidPlay when the member is not an identifier
     */
    public static function ofMember(string $member): self
    {
        return new self(PlayQueryKind::Member, Identifier::check($member, 'a member'));
    }

    /**
     * The plays where the member holds a seat they have not confirmed yet.
     *
     * @throws InvalidPlay when the member is not an identifier
     */
    public static function pendingFor(string $member): self
    {
        return new self(PlayQueryKind::Pending, Identifier::check($member, 'a member'));
    }

    /**
     * The plays the member recorded.
     *
     * @throws InvalidPlay when the member is not an identifier
     */
    public static function recordedBy(string $member): self
    {
        return new self(PlayQueryKind::RecordedBy, Identifier::check($member, 'a member'));
    }

    /**
     * The plays of the group.
     *
     * @throws InvalidPlay when the group is not an identifier
     */
    public static function ofGroup(string $group): self
    {
        return new self(PlayQueryKind::Group, Identifier::check($group, 'a group'));
    }

    /**
     * @throws InvalidPlay when the game is not an identifier
     */
    public function forGame(string $game): self
    {
        return new self($this->kind, $this->subject, Identifier::game($game), $this->from, $this->until);
    }

    /**
     * The plays from the first moment, included, to the second, excluded; either may be open.
     *
     * @throws InvalidPlay when the period ends before it starts
     */
    public function between(?DateTimeImmutable $from, ?DateTimeImmutable $until): self
    {
        if ($from instanceof DateTimeImmutable && $until instanceof DateTimeImmutable && $until < $from) {
            throw InvalidPlay::because('A period ends after it starts.');
        }

        return new self($this->kind, $this->subject, $this->game, $from, $until);
    }

    public function matches(Play $play): bool
    {
        $subject = match ($this->kind) {
            PlayQueryKind::Member => true === $play->seat($this->subject)?->confirmed,
            PlayQueryKind::Pending => false === $play->seat($this->subject)?->confirmed,
            PlayQueryKind::RecordedBy => $this->subject === $play->recordedBy,
            PlayQueryKind::Group => $this->subject === $play->group,
        };

        return $subject && (null === $this->game || $this->game === $play->game) && $this->inPeriod($play->playedAt);
    }

    private function inPeriod(?DateTimeImmutable $playedAt): bool
    {
        if (!$this->from instanceof DateTimeImmutable && !$this->until instanceof DateTimeImmutable) {
            return true;
        }

        return $playedAt instanceof DateTimeImmutable
            && (!$this->from instanceof DateTimeImmutable || $playedAt >= $this->from)
            && (!$this->until instanceof DateTimeImmutable || $playedAt < $this->until);
    }
}
