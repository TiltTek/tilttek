<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;

/**
 * The points held for the prize of a challenge: under the key `friend-challenge:` and the challenge's identifier,
 * taken from its creator, from the moment it was created until its review ends at the latest. Every operation of the
 * escrow takes the whole hold, so that an adapter builds what its own ledger needs from it.
 */
final readonly class PrizeHold
{
    public const string KEY_PREFIX = 'friend-challenge:';

    public DateTimeImmutable $heldAt;

    public DateTimeImmutable $until;

    public function __construct(
        public string $key,
        public ResourceReference $payer,
        public int $amount,
        public string $unit,
        DateTimeImmutable $heldAt,
        DateTimeImmutable $until,
    ) {
        if ($amount < 1) {
            throw InvalidChallenge::because(\sprintf('A hold is at least one point; %d is not.', $amount));
        }

        if ($until <= $heldAt) {
            throw InvalidChallenge::because(\sprintf('The hold "%s" ends before it starts.', $key));
        }

        $this->heldAt = Instant::utc($heldAt);
        $this->until = Instant::utc($until);
    }

    public static function of(Challenge $challenge): self
    {
        return new self(self::KEY_PREFIX.$challenge->id, $challenge->creator, $challenge->prize->amount, $challenge->prize->unit, $challenge->createdAt, $challenge->reviewEndsAt);
    }

    /**
     * Whether the other hold takes the same points: the same key, payer, amount and unit.
     */
    public function equals(self $other): bool
    {
        return $this->key === $other->key && $this->payer->equals($other->payer) && $this->amount === $other->amount && $this->unit === $other->unit;
    }
}
