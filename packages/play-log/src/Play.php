<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

use DateTimeImmutable;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Internal\Identifier;
use Tilttek\PlayLog\Internal\Text;

/**
 * A game played once: the game of the catalogue, the member who recorded it, its seats, how it ended, and, when known,
 * its date, its length, its group and its place. It is kept at a version, which the store raises at each write.
 */
final readonly class Play
{
    /** The most seats of a play. */
    public const int MAX_SEATS = 100;

    /** The longest play, in minutes: a day. */
    public const int MAX_MINUTES = 1_440;

    /** The longest place, in characters. */
    public const int MAX_PLACE = 120;

    /** @var list<Seat> */
    public array $seats;

    public ?string $place;

    /**
     * @param string             $id         the identifier of the play, chosen by the application
     * @param string             $game       the identifier of the game in the catalogue
     * @param string             $recordedBy the member who recorded the play, who alone corrects or deletes it
     * @param list<Seat>         $seats      1 to 100, each member once
     * @param ?DateTimeImmutable $playedAt   when the play took place, or null when the date is unknown
     * @param ?int               $minutes    how long it lasted, 1 to 1440, when known
     * @param ?string            $group      the group it belongs to, when it was played as one
     * @param ?string            $place      where it took place, one line of at most 120 characters
     *
     * @throws InvalidPlay when a value is out of its shape or its bounds
     */
    public function __construct(
        public string $id,
        public string $game,
        public string $recordedBy,
        array $seats,
        public Result $result = Result::Competitive,
        public ?DateTimeImmutable $playedAt = null,
        public ?int $minutes = null,
        public ?string $group = null,
        ?string $place = null,
        public int $version = 0,
    ) {
        Identifier::check($id, 'a play');
        Identifier::game($game);
        Identifier::check($recordedBy, 'a member');

        if (null !== $group) {
            Identifier::check($group, 'a group');
        }

        if ([] === $seats || \count($seats) > self::MAX_SEATS) {
            throw InvalidPlay::because(\sprintf('A play has 1 to %d seats.', self::MAX_SEATS));
        }

        $members = [];

        foreach ($seats as $seat) {
            if (null !== $seat->member) {
                if (isset($members[$seat->member])) {
                    throw InvalidPlay::because(\sprintf('The member %s holds one seat of a play.', $seat->member));
                }

                $members[$seat->member] = $seat;
            }

            if ($seat->winner && Result::Competitive !== $result) {
                throw InvalidPlay::because('Only the seats of a competitive play are marked as winners.');
            }
        }

        if (null !== $minutes && ($minutes < 1 || $minutes > self::MAX_MINUTES)) {
            throw InvalidPlay::because(\sprintf('A play lasts 1 to %d minutes.', self::MAX_MINUTES));
        }

        if ($version < 0) {
            throw InvalidPlay::because('The version of a play is 0 or more.');
        }

        $this->seats = $seats;
        $this->place = null === $place ? null : Text::line($place, self::MAX_PLACE, 'place of a play');
    }

    /**
     * The seat of the member, confirmed or not.
     */
    public function seat(string $member): ?Seat
    {
        foreach ($this->seats as $seat) {
            if ($member === $seat->member) {
                return $seat;
            }
        }

        return null;
    }

    /**
     * Whether the seat won: marked as a winner of a competitive play, or a seat of a cooperative play won.
     */
    public function won(Seat $seat): bool
    {
        return match ($this->result) {
            Result::Competitive => $seat->winner,
            Result::CooperativeWin => true,
            Result::CooperativeLoss, Result::Unfinished => false,
        };
    }

    /**
     * The seats that won, in their order.
     *
     * @return list<Seat>
     */
    public function winners(): array
    {
        return array_values(array_filter($this->seats, $this->won(...)));
    }

    /**
     * The members of the confirmed seats, in their order.
     *
     * @return list<string>
     */
    public function linkedMembers(): array
    {
        $members = [];

        foreach ($this->seats as $seat) {
            if (null !== $seat->member && $seat->confirmed) {
                $members[] = $seat->member;
            }
        }

        return $members;
    }

    /**
     * The play as the viewer reads it: the member who recorded it reads every seat as entered; anyone else reads the
     * seats not confirmed as anonymous, but their own. Null reads as anyone.
     */
    public function seenBy(?string $viewer): self
    {
        if ($viewer === $this->recordedBy) {
            return $this;
        }

        $seats = [];

        foreach ($this->seats as $seat) {
            $seats[] = $seat->confirmed || null === $seat->member || $viewer === $seat->member ? $seat : $seat->anonymised();
        }

        return $this->withSeats($seats);
    }

    /**
     * @param list<Seat> $seats
     *
     * @throws InvalidPlay when the seats are out of their bounds
     */
    public function withSeats(array $seats): self
    {
        return new self($this->id, $this->game, $this->recordedBy, $seats, $this->result, $this->playedAt, $this->minutes, $this->group, $this->place, $this->version);
    }

    public function withVersion(int $version): self
    {
        return new self($this->id, $this->game, $this->recordedBy, $this->seats, $this->result, $this->playedAt, $this->minutes, $this->group, $this->place, $version);
    }
}
