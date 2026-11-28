<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Internal\Identifier;

/**
 * A player of a play: a member, by their identifier, or an anonymous player, who has no name at all. A member's seat is
 * linked to them once they confirmed it — or the application's consent allowed it — and counts in their statistics
 * only then; until it is confirmed, whoever else reads the play sees it anonymous.
 */
final readonly class Seat
{
    /** The highest score a seat holds, and the opposite is the lowest. */
    public const int MAX_SCORE = 1_000_000_000;

    /**
     * @param ?string $member    the member, or null for an anonymous player
     * @param ?int    $score     the score, when the game counts one
     * @param bool    $winner    whether the seat won a competitive play
     * @param bool    $confirmed whether the member confirmed the seat; the log sets it, never the caller
     *
     * @throws InvalidPlay when the member is not an identifier, the score is out of its bounds, or an anonymous seat is
     *                     confirmed
     */
    public function __construct(
        public ?string $member = null,
        public ?int $score = null,
        public bool $winner = false,
        public bool $confirmed = false,
    ) {
        if (null !== $member) {
            Identifier::check($member, 'a member');
        } elseif ($confirmed) {
            throw InvalidPlay::because('An anonymous seat is never confirmed.');
        }

        if (null !== $score && ($score < -self::MAX_SCORE || $score > self::MAX_SCORE)) {
            throw InvalidPlay::because(\sprintf('A score is from %d to %d.', -self::MAX_SCORE, self::MAX_SCORE));
        }
    }

    /**
     * The seats of members, in the order given, without scores.
     *
     * @param list<string> $members
     *
     * @return list<self>
     *
     * @throws InvalidPlay when a member is not an identifier
     */
    public static function members(array $members): array
    {
        return array_map(static fn (string $member): self => new self($member), $members);
    }

    /**
     * Anonymous seats, as many as the guests who played.
     *
     * @return list<self>
     *
     * @throws InvalidPlay when the number is negative or above the seats of a play
     */
    public static function guests(int $guests): array
    {
        if ($guests < 0 || $guests > Play::MAX_SEATS) {
            throw InvalidPlay::because(\sprintf('A play has 0 to %d guests.', Play::MAX_SEATS));
        }

        $seats = [];

        for ($i = 0; $i < $guests; ++$i) {
            $seats[] = new self();
        }

        return $seats;
    }

    public function isAnonymous(): bool
    {
        return null === $this->member;
    }

    /**
     * Whether the seat counts for the member: it is theirs and they confirmed it.
     */
    public function isLinkedTo(string $member): bool
    {
        return $this->confirmed && $member === $this->member;
    }

    public function withConfirmed(bool $confirmed): self
    {
        return new self($this->member, $this->score, $this->winner, $confirmed);
    }

    /**
     * The same seat with no member: the score and the win stay.
     */
    public function anonymised(): self
    {
        return new self(null, $this->score, $this->winner);
    }
}
